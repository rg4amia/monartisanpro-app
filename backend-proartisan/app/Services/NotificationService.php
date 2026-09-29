<?php

namespace App\Services;

use App\Jobs\DeliverNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationTemplateService;

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

    public function __construct(private NotificationTemplateService $templates) {}

    /**
     * Notifie un utilisateur d'un événement du catalogue.
     *
     * @param  array<string, scalar|null>  $vars  Variables `{nom}` du message.
     * @param  array<string, mixed>  $data  Données transmises à l'application
     *                                      (identifiants pour ouvrir le bon écran).
     */
    public function notify(User $user, string $event, array $vars = [], array $data = []): ?Notification
    {
        $message = $this->templates->render($event, $vars);
        $channels = $message['channels'];

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
