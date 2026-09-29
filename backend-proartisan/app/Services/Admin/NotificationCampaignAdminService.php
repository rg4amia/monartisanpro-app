<?php

namespace App\Services\Admin;

use App\Models\Commune;
use App\Models\Communication;
use App\Models\NotificationCampaign;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\NotificationCampaignService;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\OneSignalService;
use App\Services\SmsService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Onglet « Campagnes push & SMS » du backoffice (Chantier 14, lot D) :
 * rédaction, ciblage, programmation, annulation, duplication, suppression et
 * envoi de test d'une campagne. Toute action est auditée (Règle d'or 17).
 */
class NotificationCampaignAdminService
{
    private const FIELDS = [
        'name', 'nature', 'push_title', 'push_body', 'sms_body',
        'channel_in_app', 'channel_push', 'channel_sms', 'open_screen', 'communication_id',
    ];

    public function __construct(
        private NotificationCampaignService $campaigns,
        private AdminActivityLogger $audit,
        private OneSignalService $oneSignal,
        private SmsService $sms,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function panelData(Request $request): array
    {
        return [
            'notificationCampaigns' => $this->list($request),
            'notificationCampaignOptions' => [
                'roles' => NotificationCampaignService::ROLES,
                'kyc_statuses' => NotificationCampaignService::KYC_STATUSES,
                'natures' => NotificationCampaign::NATURE_LABELS,
                'screens' => NotificationCampaign::SCREEN_LABELS,
                'statuses' => NotificationCampaign::STATUS_LABELS,
                'communes' => Commune::orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Commune $commune) => ['id' => $commune->id, 'name' => $commune->name])->all(),
                'communications' => Communication::publie()->latest('publie_at')->limit(50)->get(['id', 'titre'])
                    ->map(fn (Communication $communication) => ['id' => $communication->id, 'titre' => $communication->titre])->all(),
                'max_recipients' => $this->campaigns->maxRecipients(),
                'max_selected_users' => NotificationCampaignService::MAX_SELECTED_USERS,
                'sms_max_segments' => NotificationTemplateService::SMS_MAX_SEGMENTS,
            ],
        ];
    }

    /**
     * Campagnes, une page à la fois (Règle d'or 19). Destinataires et SMS
     * sont estimés pour celles qui ne sont pas encore parties.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = NotificationCampaign::query()->with(['creator:id,name', 'communication:id,titre']);

        if (array_key_exists($status = (string) $request->query('campaign_status', ''), NotificationCampaign::STATUS_LABELS)) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('campaign_search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('push_title', 'like', "%{$search}%"));
        }

        /** @var LengthAwarePaginator $page */
        $page = $query->latest('id')->paginate(20)->withQueryString();

        $page->getCollection()->transform(fn (NotificationCampaign $campaign) => $this->present($campaign));

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(NotificationCampaign $campaign): array
    {
        $target = $campaign->target_json ?? [];
        $selectedUsers = ! empty($target['user_ids'])
            ? User::whereIn('id', $target['user_ids'])->get(['id', 'name', 'phone', 'role'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone, 'role' => $user->role])->all()
            : [];

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'nature' => $campaign->nature,
            'nature_label' => NotificationCampaign::NATURE_LABELS[$campaign->nature] ?? $campaign->nature,
            'push_title' => $campaign->push_title,
            'push_body' => $campaign->push_body,
            'sms_body' => $campaign->sms_body,
            'channels' => [
                'in_app' => $campaign->channel_in_app,
                'push' => $campaign->channel_push,
                'sms' => $campaign->channel_sms,
            ],
            'target' => [
                'roles' => array_values($target['roles'] ?? []),
                'commune_ids' => array_values($target['commune_ids'] ?? []),
                'kyc_statuses' => array_values($target['kyc_statuses'] ?? []),
                'user_ids' => array_values($target['user_ids'] ?? []),
            ],
            'selected_users' => $selectedUsers,
            'open_screen' => $campaign->open_screen,
            'communication' => $campaign->communication ? ['id' => $campaign->communication->id, 'titre' => $campaign->communication->titre] : null,
            'status' => $campaign->status,
            'status_label' => NotificationCampaign::STATUS_LABELS[$campaign->status] ?? $campaign->status,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'finished_at' => $campaign->finished_at?->toIso8601String(),
            'cancelled_at' => $campaign->cancelled_at?->toIso8601String(),
            'counts' => [
                'recipients' => $campaign->recipients_count,
                'served' => $campaign->served_count,
                'sent' => $campaign->sent_count,
                'failed' => $campaign->failed_count,
            ],
            'estimate' => $campaign->isEditable() ? $this->campaigns->estimate($campaign) : null,
            'editable' => $campaign->isEditable(),
            'cancellable' => $campaign->isCancellable(),
            'deletable' => $campaign->isDeletable(),
            'created_by' => $campaign->creator?->name,
            'created_at' => $campaign->created_at?->toIso8601String(),
        ];
    }

    /**
     * Utilisateurs ciblables (jamais un administrateur, un compte anonymisé
     * ou suspendu), par nom ou téléphone, pour la désignation un à un.
     *
     * @return list<array{id: int, name: string, phone: ?string, role: string}>
     */
    public function searchUsers(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        return $this->campaigns->recipientsQuery([], NotificationCampaign::NATURE_SERVICE)
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'role'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'phone' => $user->phone, 'role' => $user->role])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input, User $actor): NotificationCampaign
    {
        $values = $this->validated($input);

        $campaign = NotificationCampaign::create($values + [
            'status' => NotificationCampaign::STATUS_DRAFT,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log('notification_campaign.created', $campaign, ['apres' => $this->snapshot($campaign)], $campaign->name, $actor);

        return $campaign;
    }

    /**
     * Modifie une campagne pas encore partie. Une campagne programmée le
     * reste, mais son nombre de destinataires est recalculé.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(NotificationCampaign $campaign, array $input, User $actor): void
    {
        $this->ensure($campaign->isEditable(), 'Une campagne partie ou annulée ne se modifie plus.');

        $before = $this->snapshot($campaign);
        $campaign->fill($this->validated($input) + ['updated_by' => $actor->id]);

        if ($campaign->status === NotificationCampaign::STATUS_SCHEDULED) {
            $campaign->recipients_count = $this->checkedRecipients($campaign);
        }
        $campaign->save();

        $this->audit->log('notification_campaign.updated', $campaign, [
            'avant' => $before,
            'apres' => $this->snapshot($campaign),
        ], $campaign->name, $actor);
    }

    public function delete(NotificationCampaign $campaign, User $actor): void
    {
        $this->ensure($campaign->isDeletable(), 'Une campagne envoyée reste dans l\'historique : elle ne se supprime pas.');

        $before = $this->snapshot($campaign);
        $campaign->delete();

        $this->audit->log('notification_campaign.deleted', null, ['avant' => $before], $before['nom'], $actor);
    }

    public function duplicate(NotificationCampaign $campaign, User $actor): NotificationCampaign
    {
        $copy = NotificationCampaign::create([
            ...$campaign->only(self::FIELDS),
            'name' => mb_substr('Copie de '.$campaign->name, 0, 120),
            'target_json' => $campaign->target_json,
            'status' => NotificationCampaign::STATUS_DRAFT,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log('notification_campaign.duplicated', $copy, ['origine' => $campaign->id], $copy->name, $actor);

        return $copy;
    }

    /**
     * Programme l'envoi (immédiat si aucune date) : le nombre de
     * destinataires est recalculé et figé, le plafond vérifié.
     *
     * @throws ValidationException
     */
    public function schedule(NotificationCampaign $campaign, ?CarbonInterface $at, User $actor): void
    {
        $this->ensure($campaign->isEditable(), 'Cette campagne est déjà partie ou annulée.');

        $errors = $this->campaigns->validate($this->input($campaign));
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $campaign->forceFill([
            'status' => NotificationCampaign::STATUS_SCHEDULED,
            'scheduled_at' => $at ?? now(),
            'recipients_count' => $this->checkedRecipients($campaign),
            'updated_by' => $actor->id,
        ])->save();

        $this->audit->log('notification_campaign.scheduled', $campaign, [
            'programmee_pour' => $campaign->scheduled_at->toIso8601String(),
            'destinataires' => $campaign->recipients_count,
            'sms' => $this->campaigns->estimate($campaign)['sms_messages'],
        ], $campaign->name, $actor);
    }

    /** Arrête une campagne programmée ou en cours ; les destinataires déjà servis le restent. */
    public function cancel(NotificationCampaign $campaign, User $actor): void
    {
        $this->ensure($campaign->isCancellable(), 'Seule une campagne programmée ou en cours peut être annulée.');

        $before = $campaign->status;
        $campaign->forceFill([
            'status' => NotificationCampaign::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
        ])->save();

        $this->audit->log('notification_campaign.cancelled', $campaign, [
            'statut_avant' => $before,
            'destinataires_servis' => $campaign->served_count,
        ], $campaign->name, $actor);
    }

    /**
     * Envoie la campagne, préfixée « [Test] », à l'administrateur seul : ni
     * notification, ni destinataire, ni ligne de journal.
     *
     * @param  list<string>  $channels  `push` et/ou `sms`
     * @return array<string, array{status: string, reason: ?string}>
     */
    public function sendTest(NotificationCampaign $campaign, array $channels, User $actor): array
    {
        $results = [];

        if (in_array('push', $channels, true) && filled($campaign->push_title) && filled($campaign->push_body)) {
            $results['push'] = $this->oneSignal->deliver(
                (string) $actor->id,
                '[Test] '.$campaign->push_title,
                (string) $campaign->push_body,
                $this->campaigns->pushData($campaign) + ['test' => true],
            );
        }

        if (in_array('sms', $channels, true) && filled($campaign->sms_body)) {
            if (blank($actor->phone)) {
                $results['sms'] = ['status' => NotificationDelivery::STATUS_SKIPPED, 'reason' => 'Aucun numéro de téléphone sur votre compte'];
            } else {
                try {
                    $response = $this->sms->send($actor->phone, '[Test] '.$campaign->sms_body);
                    $results['sms'] = ($response['status'] ?? null) === 'error'
                        ? ['status' => NotificationDelivery::STATUS_FAILED, 'reason' => (string) ($response['message'] ?? 'Échec de la passerelle SMS')]
                        : ['status' => NotificationDelivery::STATUS_SENT, 'reason' => null];
                } catch (\Throwable $e) {
                    $results['sms'] = ['status' => NotificationDelivery::STATUS_FAILED, 'reason' => $e->getMessage()];
                }
            }
        }

        $this->audit->log('notification_campaign.test_sent', $campaign, ['resultats' => $results], $campaign->name, $actor);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(array $input): array
    {
        $input['channel_sms'] = (bool) ($input['channel_sms'] ?? false);
        $errors = $this->campaigns->validate($input);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $target = $input['target'] ?? [];
        $clean = fn (string $key, bool $int) => array_values(array_unique(array_map(
            fn ($value) => $int ? (int) $value : (string) $value,
            $target[$key] ?? [],
        )));

        return [
            'name' => trim((string) $input['name']),
            'nature' => $input['nature'],
            'push_title' => filled($input['push_title'] ?? null) ? trim((string) $input['push_title']) : null,
            'push_body' => filled($input['push_body'] ?? null) ? trim((string) $input['push_body']) : null,
            'sms_body' => $input['channel_sms'] && filled($input['sms_body'] ?? null) ? trim((string) $input['sms_body']) : null,
            'channel_in_app' => (bool) ($input['channel_in_app'] ?? false),
            'channel_push' => (bool) ($input['channel_push'] ?? false),
            'channel_sms' => $input['channel_sms'],
            'target_json' => [
                'roles' => $clean('roles', false),
                'commune_ids' => $clean('commune_ids', true),
                'kyc_statuses' => $clean('kyc_statuses', false),
                'user_ids' => $clean('user_ids', true),
            ],
            'open_screen' => $input['open_screen'] ?? 'notifications',
            'communication_id' => ($input['open_screen'] ?? null) === 'communication' ? (int) $input['communication_id'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(NotificationCampaign $campaign): array
    {
        return $campaign->only(self::FIELDS) + ['target' => $campaign->target_json ?? []];
    }

    /**
     * @throws ValidationException
     */
    private function checkedRecipients(NotificationCampaign $campaign): int
    {
        $estimate = $this->campaigns->estimate($campaign);

        if ($estimate['recipients'] === 0) {
            throw ValidationException::withMessages(['target' => $campaign->nature === NotificationCampaign::NATURE_PROMOTIONAL
                ? 'Aucun destinataire : une campagne promotionnelle ne vise que les utilisateurs ayant accepté les offres et nouveautés.'
                : 'Aucun utilisateur ne correspond à ce ciblage.']);
        }
        if ($estimate['over_limit']) {
            throw ValidationException::withMessages(['target' => "Ce ciblage compte {$estimate['recipients']} destinataires : le plafond est de {$estimate['max_recipients']} par campagne."]);
        }

        return $estimate['recipients'];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(NotificationCampaign $campaign): array
    {
        return [
            'nom' => $campaign->name,
            'nature' => $campaign->nature,
            'titre' => $campaign->push_title,
            'texte' => $campaign->push_body,
            'sms' => $campaign->sms_body,
            'canaux' => ['in_app' => $campaign->channel_in_app, 'push' => $campaign->channel_push, 'sms' => $campaign->channel_sms],
            'ciblage' => $campaign->target_json,
            'ecran' => $campaign->open_screen,
            'statut' => $campaign->status,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['campaign' => $message]);
        }
    }
}
