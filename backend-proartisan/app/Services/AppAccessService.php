<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Réglages d'accès à l'application mobile par espace (client, artisan,
 * fournisseur, livreur) : mode de blocage et message affiché à l'utilisateur.
 * Stockés dans la table clé-valeur `settings`, groupe `app_access`.
 */
class AppAccessService
{
    public const BLOCK_MODES = ['none', 'new', 'old', 'all', 'hidden'];

    private const DEFAULTS = [
        'block_client' => 'none',
        'block_artisan' => 'none',
        'block_fournisseur' => 'none',
        'block_livreur' => 'none',
        'app_access_disabled_message' => 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.',
        'app_access_disabled_message_client' => 'L\'accès à l\'espace client est temporairement indisponible pour maintenance. Veuillez nous excuser pour la gêne occasionnée.',
        'app_access_disabled_message_artisan' => 'L\'accès à l\'espace artisan est temporairement suspendu. Nos équipes interviennent rapidement. Merci de votre patience.',
        'app_access_disabled_message_fournisseur' => 'L\'espace fournisseur est en cours de mise à jour technique. L\'accès sera rétabli sous peu.',
        'app_access_disabled_message_livreur' => 'L\'espace de livraison est momentanément inaccessible. Merci de réessayer d\'ici quelques instants.',
    ];

    /**
     * Réglages effectifs : valeur stockée, sinon valeur par défaut.
     *
     * @return array<string, string>
     */
    public function current(): array
    {
        $stored = Setting::query()
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->pluck('value', 'key');

        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = $stored->get($key, $default);
        }

        return $settings;
    }

    /**
     * @param  array<string, string|null>  $values  réglages déjà validés
     */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '', 'type' => 'string', 'group' => 'app_access'],
            );
        }
    }
}
