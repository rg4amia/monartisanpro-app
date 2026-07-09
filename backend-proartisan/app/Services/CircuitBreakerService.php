<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker pour les APIs de paiement Mobile Money (Wave, Orange Money).
 *
 * États :
 *   CLOSED    → les appels passent normalement
 *   OPEN      → tous les appels échouent immédiatement (fast-fail)
 *   HALF_OPEN → un seul appel de test est autorisé pour vérifier la reprise
 *
 * Seuil : 5 erreurs dans une fenêtre de 60 secondes → OPEN.
 * Repos (cooldown) : 30 secondes avant de passer à HALF_OPEN.
 */
class CircuitBreakerService
{
    public const STATE_CLOSED    = 'closed';
    public const STATE_OPEN      = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    /** Nombre d'échecs consécutifs ou dans la fenêtre pour ouvrir le circuit. */
    private const FAILURE_THRESHOLD = 5;

    /** Fenêtre de comptage des erreurs (secondes). */
    private const FAILURE_WINDOW = 60;

    /** Durée pendant laquelle le circuit reste ouvert avant de tenter un half-open (secondes). */
    private const COOLDOWN_SECONDS = 30;

    /**
     * Vérifie si le circuit est disponible pour un opérateur donné.
     * Lève une exception CircuitOpenException si le circuit est OPEN.
     *
     * @throws \App\Exceptions\CircuitOpenException
     */
    public function ensureAvailable(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_OPEN) {
            // Vérifier si le cooldown est expiré → passer en HALF_OPEN
            $openedAt = (int) Cache::get($this->cacheKey($provider, 'opened_at'), 0);
            $elapsed  = time() - $openedAt;

            if ($elapsed >= self::COOLDOWN_SECONDS) {
                $this->setState($provider, self::STATE_HALF_OPEN);
                Log::info("CircuitBreaker [{$provider}]: OPEN → HALF_OPEN (cooldown expiré après {$elapsed}s)");
                return; // autorise un appel de test
            }

            $label = $this->providerLabel($provider);
            Log::warning("CircuitBreaker [{$provider}]: circuit OUVERT — appel refusé");
            throw new \App\Exceptions\CircuitOpenException(
                "Transactions momentanément suspendues par l'opérateur {$label}."
            );
        }

        // CLOSED ou HALF_OPEN → l'appel est autorisé
    }

    /**
     * Enregistre un appel réussi → ferme le circuit.
     */
    public function recordSuccess(string $provider): void
    {
        $previousState = $this->getState($provider);
        $this->resetFailures($provider);
        $this->setState($provider, self::STATE_CLOSED);

        if ($previousState !== self::STATE_CLOSED) {
            Log::info("CircuitBreaker [{$provider}]: {$previousState} → CLOSED (succès)");
        }
    }

    /**
     * Enregistre un appel échoué → incrémente le compteur.
     * Si le seuil est atteint, ouvre le circuit.
     */
    public function recordFailure(string $provider): void
    {
        $state = $this->getState($provider);

        // En HALF_OPEN, un seul échec → retour à OPEN
        if ($state === self::STATE_HALF_OPEN) {
            $this->openCircuit($provider);
            Log::warning("CircuitBreaker [{$provider}]: HALF_OPEN → OPEN (échec du test)");
            return;
        }

        $count = $this->incrementFailures($provider);

        if ($count >= self::FAILURE_THRESHOLD) {
            $this->openCircuit($provider);
            Log::warning("CircuitBreaker [{$provider}]: CLOSED → OPEN (seuil de {$count} erreurs atteint)");
        }
    }

    /**
     * Retourne l'état courant du circuit pour un opérateur.
     */
    public function getState(string $provider): string
    {
        return Cache::get($this->cacheKey($provider, 'state'), self::STATE_CLOSED);
    }

    /**
     * Retourne le nombre d'erreurs dans la fenêtre courante.
     */
    public function getFailureCount(string $provider): int
    {
        return (int) Cache::get($this->cacheKey($provider, 'failures'), 0);
    }

    /**
     * Force la réinitialisation du circuit (utile en admin ou en tests).
     */
    public function reset(string $provider): void
    {
        $this->resetFailures($provider);
        $this->setState($provider, self::STATE_CLOSED);
        Cache::forget($this->cacheKey($provider, 'opened_at'));

        Log::info("CircuitBreaker [{$provider}]: réinitialisé manuellement → CLOSED");
    }

    // ──────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────

    private function openCircuit(string $provider): void
    {
        $this->setState($provider, self::STATE_OPEN);
        Cache::put($this->cacheKey($provider, 'opened_at'), time(), self::COOLDOWN_SECONDS + 60);
        $this->resetFailures($provider);
    }

    private function setState(string $provider, string $state): void
    {
        Cache::put($this->cacheKey($provider, 'state'), $state, now()->addMinutes(10));
    }

    private function incrementFailures(string $provider): int
    {
        $key = $this->cacheKey($provider, 'failures');

        if (!Cache::has($key)) {
            Cache::put($key, 0, self::FAILURE_WINDOW);
        }

        return Cache::increment($key);
    }

    private function resetFailures(string $provider): void
    {
        Cache::forget($this->cacheKey($provider, 'failures'));
    }

    private function cacheKey(string $provider, string $suffix): string
    {
        return "circuit_breaker:{$provider}:{$suffix}";
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'wave'         => 'Wave',
            'orange_money' => 'Orange Money',
            default        => $provider,
        };
    }
}
