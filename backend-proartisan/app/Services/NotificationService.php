<?php

namespace App\Services;

use App\Jobs\DeliverNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Point d'entrée unique des notifications (Règle d'or 79).
 *
 * Chaque appel désigne un événement du catalogue
 * (`App\Services\Notifications\NotificationCatalog`) et lui passe ses
 * variables ; le texte et les canaux viennent du catalogue ou de la
 * surcharge saisie dans le backoffice. La notification in-app est enregistrée
 * immédiatement ; push et SMS partent après la réponse HTTP
 * (`DeliverNotificationJob`), chaque tentative étant journalisée.
 */
class NotificationService
{
    /**
     * Nom (sans extension) de la sonnerie distincte, dans
     * frontend_flutter/android/app/src/main/res/raw/notif_artisan.wav.
     */
    private const DISTINCT_SOUND = 'notif_artisan';

    /**
     * Rôles bénéficiant de la sonnerie distincte (métiers de terrain : artisan
     * intervenant sur chantier, livreur en tournée).
     */
    private const ROLES_WITH_DISTINCT_SOUND = ['artisan', 'livreur'];

    public function __construct(
        private NotificationTemplateService $templates,
        private NotificationPreferenceService $preferences,
    ) {}

    /**
     * Notifie un utilisateur d'un événement du catalogue.
     *
     * @param  array<string, scalar|null>  $vars  Variables `{nom}` du message.
     * @param  array<string, mixed>  $data  Données transmises à l'application
     *                                      (identifiants pour ouvrir le bon écran).
     * @param  string|null  $smsPhone  Numéro qui reçoit le SMS à la place de
     *                                 celui du compte (ancien numéro prévenu
     *                                 d'un changement).
     */
    public function notify(User $user, string $event, array $vars = [], array $data = [], ?string $smsPhone = null): ?Notification
    {
        $message = $this->templates->render($event, $vars);
        // Préférences du destinataire : il coupe le push ou le SMS d'une
        // rubrique, jamais un message essentiel ni la notification in-app.
        $channels = $this->preferences->apply($user, $event, $message['channels']);

        $notification = null;
        if ($channels['in_app']) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $message['type'],
                'event_key' => $event,
                'title' => $message['title'],
                'body' => $message['body'],
                'data_json' => $data,
            ]);
        }

        if (! $channels['push'] && ! $channels['sms']) {
            return $notification;
        }

        // Le mobile choisit l'écran à ouvrir d'après `type` : sans lui, toucher
        // la notification n'ouvrait rien (Chantier 14, lot A).
        $push = $channels['push'] ? [
            'title' => $message['title'],
            'body' => $message['body'],
            'data' => array_merge($data, [
                'type' => $message['type'],
                'event' => $event,
                'notification_id' => $notification?->id,
            ]),
            'sound' => in_array($user->role, self::ROLES_WITH_DISTINCT_SOUND, true) ? self::DISTINCT_SOUND : null,
        ] : null;

        DeliverNotificationJob::dispatchAfterResponse(
            $user->id,
            $notification?->id,
            $event,
            $push,
            $channels['sms'] ? $message['sms'] : null,
            $smsPhone,
        );

        return $notification;
    }

    /**
     * L'utilisateur a-t-il déjà reçu l'un de ces événements (in-app) ?
     *
     * Jamais sur le titre : il devient modifiable depuis le backoffice. Les
     * notifications antérieures au catalogue, sans `event_key`, sont
     * reconnues à leur titre d'origine.
     *
     * @param  list<string>  $events
     */
    public function alreadyNotified(User $user, array $events): bool
    {
        $legacyTitles = array_map(fn (string $event) => NotificationCatalog::get($event)['title'], $events);

        return Notification::where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->whereIn('event_key', $events)
                ->orWhere(fn ($legacy) => $legacy->whereNull('event_key')->whereIn('title', $legacyTitles)))
            ->exists();
    }

    /**
     * Notifications d'un utilisateur, page par page, avec le nombre de non
     * lues et les rubriques présentes — tous deux indépendants de la page et
     * du filtre en cours.
     *
     * @return array{page: LengthAwarePaginator, unread: int, domains: list<array{key: string, label: string, total: int, unread: int}>}
     */
    public function listFor(User $user, int $perPage = 30, ?string $domain = null): array
    {
        $page = Notification::where('user_id', $user->id)
            ->when($domain !== null, fn ($query) => $query->whereIn('event_key', NotificationCatalog::eventsOfDomain($domain)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'page' => $page,
            'unread' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'domains' => $this->domainsOf($user),
        ];
    }

    /**
     * Rubriques du catalogue présentes dans les notifications de l'utilisateur.
     *
     * @return list<array{key: string, label: string, total: int, unread: int}>
     */
    private function domainsOf(User $user): array
    {
        $counts = [];
        Notification::where('user_id', $user->id)
            ->whereNotNull('event_key')
            ->selectRaw('event_key, COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
            ->groupBy('event_key')
            ->get()
            ->each(function ($row) use (&$counts) {
                $domain = NotificationCatalog::domainOf($row->event_key);
                if ($domain === null) {
                    return;
                }
                $counts[$domain]['total'] = ($counts[$domain]['total'] ?? 0) + (int) $row->total;
                $counts[$domain]['unread'] = ($counts[$domain]['unread'] ?? 0) + (int) $row->unread;
            });

        $domains = [];
        foreach (NotificationCatalog::DOMAINS as $key => $label) {
            if (isset($counts[$key])) {
                $domains[] = ['key' => $key, 'label' => $label, ...$counts[$key]];
            }
        }

        return $domains;
    }

    /**
     * Marque une notification comme lue. La date de première lecture est
     * conservée : c'est elle qui fait courir le délai de purge.
     */
    public function markRead(Notification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Supprime les notifications lues depuis plus longtemps que le délai de
     * conservation. Une notification non lue n'est jamais purgée.
     */
    public function purgeRead(): int
    {
        $months = (int) config('prosartisan.notifications.read_retention_months', 12);

        return Notification::whereNotNull('read_at')
            ->where('read_at', '<', now()->subMonths($months))
            ->delete();
    }

    /**
     * Notifie tous les administrateurs d'un événement du catalogue.
     *
     * @param  array<string, scalar|null>  $vars
     * @param  array<string, mixed>  $data
     */
    public function notifyAdmins(string $event, array $vars = [], array $data = []): void
    {
        User::where('role', 'admin')->get()->each(
            fn (User $admin) => $this->notify($admin, $event, $vars, $data)
        );
    }
}
