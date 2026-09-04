<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Anti-bruteforce de la connexion admin (Chantier C3 / P0-5).
 *
 * 5 tentatives échouées par (identifiant + IP) et par minute, puis blocage
 * temporaire avec message français. Le compteur est incrémenté sur chaque
 * échec (identifiants ou 2FA) et purgé à la connexion réussie.
 *
 * Implémenté côté service — et non via le middleware `throttle:` — pour rester
 * actif en environnement local (où les limiters nommés sont désactivés) et
 * testable de bout en bout.
 */
class AdminLoginThrottle
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function key(Request $request, ?string $identifier = null): string
    {
        $identifier ??= (string) $request->input('identifier', '');

        return 'admin-login:'.Str::transliterate(Str::lower(trim($identifier))).'|'.$request->ip();
    }

    /**
     * À appeler avant de vérifier les identifiants. Lève une ValidationException
     * (message français, champ `identifier`) si le seuil est atteint.
     */
    public function ensureIsNotLimited(Request $request, ?string $identifier = null): void
    {
        $key = $this->key($request, $identifier);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        $minutes = (int) ceil($seconds / 60);

        throw ValidationException::withMessages([
            'identifier' => "Trop de tentatives de connexion. Réessayez dans {$minutes} minute".($minutes > 1 ? 's' : '').'.',
        ])->status(429);
    }

    public function hit(Request $request, ?string $identifier = null): void
    {
        RateLimiter::hit($this->key($request, $identifier), self::DECAY_SECONDS);
    }

    public function clear(Request $request, ?string $identifier = null): void
    {
        RateLimiter::clear($this->key($request, $identifier));
    }
}
