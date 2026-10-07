<?php

namespace App\Rules;

use App\Support\PrivateMedia;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * N'accepte que l'adresse d'un fichier de la plateforme : fichier privé
 * (`/media/prive/…`) ou ancienne adresse publique (`/storage/…`), sur le
 * domaine de l'API.
 *
 * Une photo de demande de mission ou d'étape s'accompagnait de n'importe
 * quelle chaîne. L'application ouvre hors d'elle-même une adresse de vidéo :
 * l'autre partie du chantier était envoyée sur le site choisi par l'auteur —
 * hameçonnage, ou page portant un numéro, hors du filtre anti-contournement.
 *
 * La règle ne juge que l'origine de l'adresse. La signature d'un fichier
 * privé reste contrôlée à l'enregistrement (`PrivateMedia::toStored`).
 */
class PlatformFileUrl implements ValidationRule
{
    private const PUBLIC_PREFIX = '/storage/';

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::accepts($value)) {
            $fail('Ce fichier doit être envoyé depuis l\'application.');
        }
    }

    public static function accepts(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        $parts = parse_url($value);
        if ($parts === false || ! isset($parts['path'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Une adresse sans domaine (`/storage/…`) désigne la plateforme ; un
        // domaine sans schéma (`//site.example/…`) n'est jamais accepté.
        if (isset($parts['host']) && (! isset($parts['scheme']) || ! self::isPlatformHost($parts['host']))) {
            return false;
        }

        $path = $parts['path'];
        if (str_contains(rawurldecode($path), '..')) {
            return false;
        }

        return str_starts_with($path, self::PUBLIC_PREFIX) || PrivateMedia::isPrivateUrl($value);
    }

    private static function isPlatformHost(string $host): bool
    {
        $hosts = array_filter([
            request()->getHost(),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);

        return in_array(strtolower($host), array_map('strtolower', $hosts), true);
    }
}
