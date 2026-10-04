<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Préférences de notification d'un utilisateur (Chantier 14, lot E).
 *
 * L'utilisateur coupe le push ou le SMS rubrique par rubrique. Les messages
 * essentiels (`NotificationCatalog::isCritical`) partent toujours, et la
 * notification reste dans la liste de l'application quel que soit le réglage.
 * L'accord aux offres et nouveautés est distinct, et désactivé par défaut.
 */
class NotificationPreferenceService
{
    public const ESSENTIAL_MESSAGE = 'Les messages essentiels de cette rubrique ne se désactivent pas.';

    public const UNKNOWN_DOMAIN_MESSAGE = 'Rubrique de notification inconnue.';

    public function __construct(private NotificationTemplateService $templates) {}

    /**
     * Préférences telles que l'application les affiche.
     *
     * @return array{promotional_push: bool, domains: list<array{key: string, label: string, push: bool, sms: bool, push_editable: bool, sms_editable: bool, essential: bool}>}
     */
    public function for(User $user): array
    {
        $preference = NotificationPreference::firstWhere('user_id', $user->id);
        $mutedPush = $preference?->muted_push_domains ?? [];
        $mutedSms = $preference?->muted_sms_domains ?? [];

        $domains = [];
        foreach ($this->domainsFor($user) as $key => $domain) {
            $domains[] = [
                'key' => $key,
                'label' => NotificationCatalog::DOMAINS[$key],
                'push' => ! $domain['push_editable'] || ! in_array($key, $mutedPush, true),
                'sms' => $domain['sms_editable'] && ! in_array($key, $mutedSms, true),
                'push_editable' => $domain['push_editable'],
                'sms_editable' => $domain['sms_editable'],
                'essential' => $domain['essential'],
            ];
        }

        return [
            'promotional_push' => (bool) ($preference?->promotional_push ?? false),
            'domains' => $domains,
        ];
    }

    /**
     * Enregistre les réglages transmis ; les rubriques absentes ne changent pas.
     *
     * @param  array{promotional_push?: bool, domains?: array<string, array{push?: bool, sms?: bool}>}  $input
     * @return array<string, mixed>
     */
    public function update(User $user, array $input): array
    {
        $available = $this->domainsFor($user);
        $preference = NotificationPreference::firstOrNew(['user_id' => $user->id]);
        $muted = [
            'push' => $preference->muted_push_domains ?? [],
            'sms' => $preference->muted_sms_domains ?? [],
        ];

        foreach ($input['domains'] ?? [] as $key => $channels) {
            if (! isset($available[$key])) {
                throw ValidationException::withMessages(['domains' => self::UNKNOWN_DOMAIN_MESSAGE]);
            }

            foreach (['push', 'sms'] as $channel) {
                if (! array_key_exists($channel, $channels)) {
                    continue;
                }

                $enabled = (bool) $channels[$channel];
                if (! $enabled && ! $available[$key]["{$channel}_editable"]) {
                    throw ValidationException::withMessages(['domains' => self::ESSENTIAL_MESSAGE]);
                }

                $muted[$channel] = $enabled
                    ? array_values(array_diff($muted[$channel], [$key]))
                    : array_values(array_unique([...$muted[$channel], $key]));
            }
        }

        $preference->muted_push_domains = $muted['push'] ?: null;
        $preference->muted_sms_domains = $muted['sms'] ?: null;

        if (array_key_exists('promotional_push', $input)) {
            $accepted = (bool) $input['promotional_push'];
            if ($accepted !== (bool) $preference->promotional_push) {
                $preference->promotional_push_at = $accepted ? now() : null;
            }
            $preference->promotional_push = $accepted;
        }

        $preference->save();

        return $this->for($user);
    }

    /**
     * Canaux d'un envoi, une fois les préférences du destinataire appliquées.
     * La notification in-app n'est jamais retirée.
     *
     * @param  array{in_app: bool, push: bool, sms: bool}  $channels
     * @return array{in_app: bool, push: bool, sms: bool}
     */
    public function apply(User $user, string $event, array $channels): array
    {
        if ((! $channels['push'] && ! $channels['sms']) || NotificationCatalog::isCritical($event)) {
            return $channels;
        }

        $preference = NotificationPreference::firstWhere('user_id', $user->id);
        if ($preference === null) {
            return $channels;
        }

        $domain = NotificationCatalog::domainOf($event);
        if (in_array($domain, $preference->muted_push_domains ?? [], true)) {
            $channels['push'] = false;
        }
        if (in_array($domain, $preference->muted_sms_domains ?? [], true)) {
            $channels['sms'] = false;
        }

        return $channels;
    }

    /**
     * Rubriques dont le rôle reçoit des messages, avec ce qu'il peut y couper :
     * un canal n'est réglable que si un message non essentiel l'emploie.
     *
     * @return array<string, array{push_editable: bool, sms_editable: bool, essential: bool}>
     */
    private function domainsFor(User $user): array
    {
        $found = [];
        foreach (NotificationCatalog::eventsForRole((string) $user->role) as $event) {
            $domain = NotificationCatalog::domainOf($event);
            $found[$domain] ??= ['push_editable' => false, 'sms_editable' => false, 'essential' => false];

            if (NotificationCatalog::isCritical($event)) {
                $found[$domain]['essential'] = true;

                continue;
            }

            $channels = $this->templates->resolve($event)['channels'];
            $found[$domain]['push_editable'] = $found[$domain]['push_editable'] || $channels['push'];
            $found[$domain]['sms_editable'] = $found[$domain]['sms_editable'] || $channels['sms'];
        }

        // Ordre du catalogue, identique pour tous les rôles.
        $ordered = [];
        foreach (array_keys(NotificationCatalog::DOMAINS) as $key) {
            if (isset($found[$key])) {
                $ordered[$key] = $found[$key];
            }
        }

        return $ordered;
    }
}
