<?php

namespace App\Services\Notifications;

use App\Models\Communication;
use App\Models\Notification;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\OneSignalService;
use App\Services\SmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Campagnes push et SMS (Chantier 14, lot D) : règles de rédaction, ciblage,
 * estimation et envoi par lots. L'envoi est piloté par la commande planifiée
 * `notifications:send-campaigns` : un envoi de masse ne tient pas dans une
 * requête HTTP. Un destinataire déjà servi n'est jamais relancé.
 */
class NotificationCampaignService
{
    /** Rôles ciblables : jamais les administrateurs. */
    public const ROLES = [
        'client' => 'Clients',
        'artisan' => 'Artisans',
        'fournisseur' => 'Fournisseurs',
        'livreur' => 'Livreurs',
        'referent' => 'Référents',
    ];

    public const KYC_STATUSES = [
        'actif' => 'KYC validé',
        'en_attente' => 'KYC en attente',
        'rejete' => 'KYC rejeté',
    ];

    /** Utilisateurs désignés un à un, au plus. */
    public const MAX_SELECTED_USERS = 500;

    /** Numéros par appel à la passerelle SMS. */
    private const SMS_CHUNK = 100;

    private const PLACEHOLDER = '/\{[^{}]*\}/';

    public function __construct(
        private OneSignalService $oneSignal,
        private SmsService $sms,
        private NotificationTemplateService $templates,
        private AdminActivityLogger $audit,
    ) {}

    public function maxRecipients(): int
    {
        return max(1, (int) config('prosartisan.notifications.campaign_max_recipients', 20000));
    }

    public function batchSize(): int
    {
        return max(1, (int) config('prosartisan.notifications.campaign_batch_size', 1000));
    }

    /**
     * Règles métier d'une campagne, au-delà des types contrôlés par le
     * contrôleur. Renvoie les erreurs par champ (vide = valide).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function validate(array $input): array
    {
        $errors = [];
        $inApp = (bool) ($input['channel_in_app'] ?? false);
        $push = (bool) ($input['channel_push'] ?? false);
        $sms = (bool) ($input['channel_sms'] ?? false);

        if (! $inApp && ! $push && ! $sms) {
            $errors['channels'] = 'Activez au moins un canal.';
        }

        // Décision métier n° 2 : une campagne promotionnelle part en push uniquement.
        if ($sms && ($input['nature'] ?? null) === NotificationCampaign::NATURE_PROMOTIONAL) {
            $errors['channel_sms'] = 'Une campagne promotionnelle part en push uniquement : le SMS est réservé aux informations de service.';
        }

        if ($inApp || $push) {
            if (blank($input['push_title'] ?? null)) {
                $errors['push_title'] = 'Le titre est obligatoire pour une notification dans l\'application ou push.';
            }
            if (blank($input['push_body'] ?? null)) {
                $errors['push_body'] = 'Le texte est obligatoire pour une notification dans l\'application ou push.';
            }
        }

        if ($sms) {
            $body = (string) ($input['sms_body'] ?? '');
            if (blank($body)) {
                $errors['sms_body'] = 'Le texte du SMS est obligatoire quand le canal SMS est actif.';
            } elseif (($segments = $this->templates->smsSegments($body)['segments']) > NotificationTemplateService::SMS_MAX_SEGMENTS) {
                $errors['sms_body'] = "Ce SMS compte {$segments} segments : ".NotificationTemplateService::SMS_MAX_SEGMENTS.' au maximum.';
            }
        }

        // Aucune variable : le même texte part à tous, jamais « {nom} » tel quel (Règle d'or 29).
        foreach (['push_title', 'push_body', 'sms_body'] as $field) {
            if (! isset($errors[$field]) && preg_match(self::PLACEHOLDER, (string) ($input[$field] ?? ''), $match)) {
                $errors[$field] = "Les campagnes n'acceptent pas de variable : « {$match[0]} » serait envoyé tel quel.";
            }
        }

        $target = $input['target'] ?? [];
        if (empty($target['roles']) && empty($target['user_ids'])) {
            $errors['target'] = 'Choisissez au moins un public (rôle) ou des utilisateurs précis.';
        }

        if (($input['open_screen'] ?? 'notifications') === 'communication') {
            $communicationId = $input['communication_id'] ?? null;
            if (! $communicationId || ! Communication::publie()->whereKey($communicationId)->exists()) {
                $errors['communication_id'] = 'Choisissez une communication publiée.';
            }
        }

        return $errors;
    }

    /**
     * Utilisateurs visés : critères cumulés, jamais un administrateur, un
     * compte suspendu, anonymisé ou supprimé ; une campagne promotionnelle ne
     * vise que les comptes ayant accepté les messages promotionnels.
     *
     * @param  array{roles?: list<string>, commune_ids?: list<int>, kyc_statuses?: list<string>, user_ids?: list<int>}  $target
     * @return Builder<User>
     */
    public function recipientsQuery(array $target, string $nature): Builder
    {
        $query = User::query()
            ->whereIn('role', array_keys(self::ROLES))
            ->where(fn (Builder $q) => $q->whereNull('account_status')->orWhere('account_status', 'actif'))
            ->whereNull('anonymized_at');

        if (! empty($target['roles'])) {
            $query->whereIn('role', $target['roles']);
        }
        if (! empty($target['commune_ids'])) {
            $query->whereIn('commune_id', $target['commune_ids']);
        }
        if (! empty($target['kyc_statuses'])) {
            $query->whereIn('kyc_status', $target['kyc_statuses']);
        }
        if (! empty($target['user_ids'])) {
            $query->whereIn('id', $target['user_ids']);
        }
        if ($nature === NotificationCampaign::NATURE_PROMOTIONAL) {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('notification_preferences')
                ->whereColumn('notification_preferences.user_id', 'users.id')
                ->where('notification_preferences.promotional_push', true));
        }

        return $query;
    }

    /**
     * Nombre de destinataires et de SMS, calculés côté serveur. Jamais de
     * montant : aucun coût n'est affiché (décision métier n° 3).
     *
     * @return array{recipients: int, max_recipients: int, over_limit: bool, sms_recipients: int, sms_segments: int, sms_messages: int}
     */
    public function estimate(NotificationCampaign $campaign): array
    {
        $query = $this->recipientsQuery($campaign->target_json ?? [], $campaign->nature);
        $recipients = (clone $query)->count();
        $smsRecipients = 0;
        $segments = 0;

        if ($campaign->channel_sms && filled($campaign->sms_body)) {
            $smsRecipients = (clone $query)->where('phone', '!=', '')->whereNotNull('phone')->count();
            $segments = $this->templates->smsSegments($campaign->sms_body)['segments'];
        }

        return [
            'recipients' => $recipients,
            'max_recipients' => $this->maxRecipients(),
            'over_limit' => $recipients > $this->maxRecipients(),
            'sms_recipients' => $smsRecipients,
            'sms_segments' => $segments,
            'sms_messages' => $smsRecipients * $segments,
        ];
    }

    /**
     * Passage de la commande planifiée : les campagnes arrivées à échéance
     * démarrent, puis chaque campagne en cours sert un lot de destinataires.
     *
     * @return array<int, int> destinataires servis par campagne
     */
    public function processDue(): array
    {
        $due = NotificationCampaign::where('status', NotificationCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->pluck('id');

        foreach ($due as $id) {
            // Mise à jour conditionnelle : un seul passage démarre la campagne.
            NotificationCampaign::whereKey($id)
                ->where('status', NotificationCampaign::STATUS_SCHEDULED)
                ->update(['status' => NotificationCampaign::STATUS_SENDING, 'started_at' => now()]);
        }

        $served = [];
        foreach (NotificationCampaign::where('status', NotificationCampaign::STATUS_SENDING)->orderBy('id')->get() as $campaign) {
            try {
                $served[$campaign->id] = $this->sendBatch($campaign);
            } catch (\Throwable $e) {
                // Une campagne en échec ne bloque pas les suivantes ; elle reprend au passage suivant.
                Log::error('[Campagnes] Lot impossible : '.$e->getMessage(), ['campaign_id' => $campaign->id]);
                $served[$campaign->id] = 0;
            }
        }

        return $served;
    }

    /**
     * Sert un lot de destinataires pas encore servis ; termine la campagne
     * quand il n'en reste plus ou que le plafond est atteint.
     */
    public function sendBatch(NotificationCampaign $campaign): int
    {
        $limit = min($this->batchSize(), max(0, $this->maxRecipients() - $campaign->served_count));

        $users = $limit === 0 ? collect() : $this->recipientsQuery($campaign->target_json ?? [], $campaign->nature)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('notification_campaign_recipients')
                ->whereColumn('notification_campaign_recipients.user_id', 'users.id')
                ->where('notification_campaign_recipients.campaign_id', $campaign->id))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'phone', 'role']);

        $served = $this->claim($campaign, $users);

        [$sent, $failed] = $served->isEmpty() ? [0, 0] : $this->deliver($campaign, $served);

        $campaign->forceFill([
            'served_count' => $campaign->served_count + $served->count(),
            'sent_count' => $campaign->sent_count + $sent,
            'failed_count' => $campaign->failed_count + $failed,
        ])->save();

        if ($users->count() < $limit || $limit === 0 || $campaign->served_count >= $this->maxRecipients()) {
            $this->finish($campaign);
        }

        return $served->count();
    }

    /**
     * Inscrit chaque destinataire (et sa notification in-app) avant tout
     * envoi : la clé unique écarte un destinataire déjà servi.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function claim(NotificationCampaign $campaign, Collection $users): Collection
    {
        $claimed = collect();

        foreach ($users as $user) {
            try {
                DB::transaction(function () use ($campaign, $user) {
                    $notification = $campaign->channel_in_app ? Notification::create([
                        'user_id' => $user->id,
                        'type' => 'campaign',
                        'event_key' => 'campagne',
                        'title' => $campaign->push_title,
                        'body' => $campaign->push_body,
                        'data_json' => $this->pushData($campaign),
                    ]) : null;

                    NotificationCampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'user_id' => $user->id,
                        'notification_id' => $notification?->id,
                    ]);
                });
                $claimed->push($user);
            } catch (UniqueConstraintViolationException) {
                // Déjà servi par un passage concurrent : jamais de second envoi.
            }
        }

        return $claimed;
    }

    /**
     * Push en envois groupés, SMS par paquets ; une ligne de journal par
     * destinataire et par canal.
     *
     * @param  Collection<int, User>  $users
     * @return array{0: int, 1: int} envois réussis, envois échoués
     */
    private function deliver(NotificationCampaign $campaign, Collection $users): array
    {
        $rows = [];

        if ($campaign->channel_push) {
            foreach ($users->chunk(OneSignalService::MAX_EXTERNAL_IDS) as $chunk) {
                $result = $this->oneSignal->deliverToMany(
                    $chunk->pluck('id')->all(),
                    (string) $campaign->push_title,
                    (string) $campaign->push_body,
                    $this->pushData($campaign),
                );
                foreach ($chunk as $user) {
                    $rows[] = $this->row($campaign, $user, NotificationDelivery::CHANNEL_PUSH, 'onesignal', $result['status'], $result['reason']);
                }
            }
        }

        if ($campaign->channel_sms && filled($campaign->sms_body)) {
            $provider = $this->sms->getProvider();
            [$withPhone, $withoutPhone] = $users->partition(fn (User $user) => filled($user->phone));

            foreach ($withoutPhone as $user) {
                $rows[] = $this->row($campaign, $user, NotificationDelivery::CHANNEL_SMS, $provider, NotificationDelivery::STATUS_SKIPPED, 'Aucun numéro de téléphone');
            }

            foreach ($withPhone->chunk(self::SMS_CHUNK) as $chunk) {
                try {
                    // Route `plain` : la route `otp` est réservée aux codes (Règle d'or 39).
                    $response = $this->sms->send($chunk->pluck('phone')->values()->all(), (string) $campaign->sms_body);
                    $result = ($response['status'] ?? null) === 'error'
                        ? [NotificationDelivery::STATUS_FAILED, (string) ($response['message'] ?? 'Échec de la passerelle SMS')]
                        : [NotificationDelivery::STATUS_SENT, null];
                } catch (\Throwable $e) {
                    Log::error('[Campagnes] Envoi SMS impossible : '.$e->getMessage(), ['campaign_id' => $campaign->id]);
                    $result = [NotificationDelivery::STATUS_FAILED, $e->getMessage()];
                }
                foreach ($chunk as $user) {
                    $rows[] = $this->row($campaign, $user, NotificationDelivery::CHANNEL_SMS, $provider, $result[0], $result[1]);
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            try {
                NotificationDelivery::insert($chunk);
            } catch (\Throwable $e) {
                // Le journal ne doit jamais faire échouer un envoi déjà effectué.
                Log::warning('[Campagnes] Journal des envois indisponible : '.$e->getMessage());
            }
        }

        $statuses = array_column($rows, 'status');

        return [
            count(array_keys($statuses, NotificationDelivery::STATUS_SENT, true)),
            count(array_keys($statuses, NotificationDelivery::STATUS_FAILED, true)),
        ];
    }

    private function finish(NotificationCampaign $campaign): void
    {
        $campaign->refresh();
        if ($campaign->status !== NotificationCampaign::STATUS_SENDING) {
            return;
        }

        $campaign->forceFill(['status' => NotificationCampaign::STATUS_SENT, 'finished_at' => now()])->save();

        $this->audit->log('notification_campaign.sent', $campaign, [
            'destinataires' => $campaign->served_count,
            'envois_reussis' => $campaign->sent_count,
            'envois_echoues' => $campaign->failed_count,
        ], $campaign->name, $campaign->updated_by ? User::find($campaign->updated_by) : null);
    }

    /**
     * Données du push et de la notification in-app : le mobile ouvre l'écran
     * choisi d'après `type` = `campaign` et `screen`.
     *
     * @return array<string, mixed>
     */
    public function pushData(NotificationCampaign $campaign): array
    {
        return array_filter([
            'type' => 'campaign',
            'event' => 'campagne',
            'campaign_id' => $campaign->id,
            'screen' => $campaign->open_screen,
            'communication_id' => $campaign->open_screen === 'communication' ? $campaign->communication_id : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(NotificationCampaign $campaign, User $user, string $channel, ?string $provider, string $status, ?string $reason): array
    {
        return [
            'notification_id' => null,
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'event_key' => 'campagne',
            'channel' => $channel,
            'provider' => $provider,
            'status' => $status,
            'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            'created_at' => now(),
        ];
    }
}
