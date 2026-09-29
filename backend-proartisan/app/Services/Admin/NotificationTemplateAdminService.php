<?php

namespace App\Services\Admin;

use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\OneSignalService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Onglet « Messages push & SMS » du backoffice (Chantier 14, lot C) :
 * consultation et modification des textes et canaux de chaque événement du
 * catalogue, retour au texte d'origine, envoi de test à soi-même et journal
 * des envois. Toute action est auditée (Règle d'or 17).
 */
class NotificationTemplateAdminService
{
    public const DELIVERY_STATUS_LABELS = [
        NotificationDelivery::STATUS_SENT => 'Envoyé',
        NotificationDelivery::STATUS_FAILED => 'Échoué',
        NotificationDelivery::STATUS_SKIPPED => 'Ignoré',
    ];

    private const CHANNELS = ['in_app', 'push', 'sms'];

    public function __construct(
        private NotificationTemplateService $templates,
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
            'notificationEvents' => $this->events(),
            'notificationDomains' => NotificationCatalog::DOMAINS,
            'notificationAudiences' => NotificationCatalog::AUDIENCES,
            'notificationSmsMaxSegments' => NotificationTemplateService::SMS_MAX_SEGMENTS,
            'notificationDeliveries' => $this->deliveries($request),
            'notificationDeliveryStats' => $this->deliveryStats(),
        ];
    }

    /**
     * Événements du catalogue avec leurs textes d'origine et actuels.
     *
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        $templates = NotificationTemplate::with('editor:id,name')->get()->keyBy('event_key');
        $events = [];

        foreach (NotificationCatalog::all() as $key => $_) {
            $definition = NotificationCatalog::get($key);
            $resolved = $this->templates->resolve($key);
            $template = $templates->get($key);

            $events[] = [
                'key' => $key,
                'label' => $definition['label'],
                'domain' => $definition['domain'],
                'audience' => $definition['audience'],
                'variables' => collect($definition['variables'])
                    ->map(fn (string $description, string $name) => [
                        'name' => $name,
                        'description' => $description,
                        'example' => NotificationCatalog::example($name),
                        'required' => in_array($name, $definition['required'], true),
                    ])
                    ->values()
                    ->all(),
                'defaults' => [
                    'title' => $definition['title'],
                    'body' => $definition['body'],
                    'sms_body' => $definition['sms_body'],
                    'channels' => $this->templates->defaultChannels($key),
                ],
                'current' => [
                    'title' => $resolved['title'],
                    'body' => $resolved['body'],
                    'sms_body' => $template?->sms_body,
                    'channels' => $resolved['channels'],
                ],
                'locked' => (object) $definition['locked'],
                'security' => $definition['domain'] === 'securite',
                'overridden' => $template !== null,
                'updated_at' => $template?->updated_at?->toIso8601String(),
                'updated_by' => $template?->editor?->name,
            ];
        }

        return $events;
    }

    /**
     * Enregistre les textes et canaux d'un événement. Une valeur identique à
     * l'origine n'est pas stockée : le badge « Modifié » ne s'affiche que pour
     * un vrai changement, et une surcharge vidée revient au texte d'origine.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(string $event, array $input, User $actor): void
    {
        $definition = NotificationCatalog::get($event);

        $errors = $this->templates->validate($event, $input);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $before = $this->snapshot($event);
        $defaults = $this->templates->defaultChannels($event);

        $values = [
            'push_title' => $this->changed($input['push_title'] ?? null, $definition['title']),
            'push_body' => $this->changed($input['push_body'] ?? null, $definition['body']),
            'sms_body' => $this->changed($input['sms_body'] ?? null, $definition['sms_body']),
        ];
        foreach (self::CHANNELS as $channel) {
            $requested = $input["channel_{$channel}"] ?? null;
            $values["channel_{$channel}"] = $requested === null
                || array_key_exists($channel, $definition['locked'])
                || (bool) $requested === $defaults[$channel]
                ? null
                : (bool) $requested;
        }

        if (array_filter($values, fn ($value) => $value !== null) === []) {
            NotificationTemplate::where('event_key', $event)->delete();
        } else {
            NotificationTemplate::updateOrCreate(['event_key' => $event], $values + ['updated_by' => $actor->id]);
        }

        $this->templates->forgetCache();

        $this->audit->log('notification_template.updated', NotificationTemplate::where('event_key', $event)->first(), [
            'event' => $event,
            'avant' => $before,
            'apres' => $this->snapshot($event),
        ], $definition['label'], $actor);
    }

    /**
     * Supprime la surcharge : le texte et les canaux d'origine s'appliquent.
     */
    public function reset(string $event, User $actor): void
    {
        $definition = NotificationCatalog::get($event);
        $before = $this->snapshot($event);

        NotificationTemplate::where('event_key', $event)->delete();
        $this->templates->forgetCache();

        $this->audit->log('notification_template.reset', null, [
            'event' => $event,
            'avant' => $before,
        ], $definition['label'], $actor);
    }

    /**
     * Envoie le message actuel, rempli de valeurs d'exemple, à l'administrateur
     * lui-même. Rien n'est enregistré dans les notifications ni dans le journal
     * des envois : seul l'audit garde la trace du test.
     *
     * @param  list<string>  $channels  `push` et/ou `sms`
     * @return array<string, array{status: string, reason: ?string}>
     */
    public function sendTest(string $event, array $channels, User $actor): array
    {
        $definition = NotificationCatalog::get($event);
        $examples = [];
        foreach (array_keys($definition['variables']) as $name) {
            $examples[$name] = NotificationCatalog::example($name);
        }
        $message = $this->templates->render($event, $examples);
        $results = [];

        // Un message interdit de push (code OTP) ne se teste que par SMS.
        if (in_array('push', $channels, true) && ($definition['locked']['push'] ?? true) !== false) {
            $results['push'] = $this->oneSignal->deliver(
                (string) $actor->id,
                '[Test] '.$message['title'],
                $message['body'],
                ['type' => $message['type'], 'event' => $event, 'test' => true],
            );
        }

        if (in_array('sms', $channels, true)) {
            if (blank($actor->phone)) {
                $results['sms'] = ['status' => NotificationDelivery::STATUS_SKIPPED, 'reason' => 'Aucun numéro de téléphone sur votre compte'];
            } else {
                try {
                    $response = $this->sms->send($actor->phone, '[Test] '.$message['sms']);
                    $results['sms'] = ($response['status'] ?? null) === 'error'
                        ? ['status' => NotificationDelivery::STATUS_FAILED, 'reason' => (string) ($response['message'] ?? 'Échec de la passerelle SMS')]
                        : ['status' => NotificationDelivery::STATUS_SENT, 'reason' => null];
                } catch (\Throwable $e) {
                    $results['sms'] = ['status' => NotificationDelivery::STATUS_FAILED, 'reason' => $e->getMessage()];
                }
            }
        }

        $this->audit->log('notification_template.test_sent', null, [
            'event' => $event,
            'resultats' => $results,
        ], $definition['label'], $actor);

        return $results;
    }

    /**
     * Journal des envois, une page à la fois (Règle d'or 19).
     */
    public function deliveries(Request $request): LengthAwarePaginator
    {
        $query = NotificationDelivery::query()->with('user:id,name,phone,role');

        if (in_array($status = (string) $request->query('delivery_status', ''), array_keys(self::DELIVERY_STATUS_LABELS), true)) {
            $query->where('status', $status);
        }
        if (in_array($channel = (string) $request->query('delivery_channel', ''), [NotificationDelivery::CHANNEL_PUSH, NotificationDelivery::CHANNEL_SMS], true)) {
            $query->where('channel', $channel);
        }
        if ($search = trim((string) $request->query('delivery_search', ''))) {
            $events = array_keys(array_filter(
                NotificationCatalog::all(),
                fn (array $event, string $key) => str_contains(mb_strtolower($event['label']), mb_strtolower($search)) || str_contains($key, $search),
                ARRAY_FILTER_USE_BOTH,
            ));
            $query->where(function ($q) use ($search, $events) {
                $q->whereIn('event_key', $events)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        /** @var LengthAwarePaginator $page */
        $page = $query->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        $page->getCollection()->transform(fn (NotificationDelivery $delivery) => [
            'id' => $delivery->id,
            'event' => $delivery->event_key,
            'event_label' => NotificationCatalog::has((string) $delivery->event_key)
                ? NotificationCatalog::get($delivery->event_key)['label']
                : $delivery->event_key,
            'channel' => $delivery->channel,
            'provider' => $delivery->provider,
            'status' => $delivery->status,
            'status_label' => self::DELIVERY_STATUS_LABELS[$delivery->status] ?? $delivery->status,
            'reason' => $delivery->reason,
            'user' => $delivery->user ? [
                'id' => $delivery->user->id,
                'name' => $delivery->user->name,
                'role' => $delivery->user->role,
            ] : null,
            'created_at' => $delivery->created_at?->toIso8601String(),
        ]);

        return $page;
    }

    /**
     * Totaux sur 24 h, indépendants de la page et des filtres (Règle d'or 19).
     *
     * @return array{sent: int, failed: int, skipped: int, sms_sent: int}
     */
    public function deliveryStats(): array
    {
        $since = now()->subDay();
        $counts = NotificationDelivery::where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'sent' => (int) ($counts[NotificationDelivery::STATUS_SENT] ?? 0),
            'failed' => (int) ($counts[NotificationDelivery::STATUS_FAILED] ?? 0),
            'skipped' => (int) ($counts[NotificationDelivery::STATUS_SKIPPED] ?? 0),
            'sms_sent' => NotificationDelivery::where('created_at', '>=', $since)
                ->where('channel', NotificationDelivery::CHANNEL_SMS)
                ->where('status', NotificationDelivery::STATUS_SENT)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(string $event): array
    {
        $resolved = $this->templates->resolve($event);

        return [
            'titre' => $resolved['title'],
            'corps' => $resolved['body'],
            'sms' => $resolved['sms_body'],
            'canaux' => $resolved['channels'],
        ];
    }

    private function changed(mixed $value, ?string $original): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value === $original ? null : $value;
    }
}
