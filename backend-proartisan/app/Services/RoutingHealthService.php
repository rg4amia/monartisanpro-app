<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * État des services d'itinéraire qui tarifent les courses (Chantier 35).
 *
 * Yandex est le fournisseur officiel ; quand il ne répond plus, chaque course
 * est tarifée par le serveur public d'OSRM, puis par une estimation. La clé
 * Yandex de production a expiré sans que personne ne soit prévenu : ce service
 * retient le dernier succès et les échecs consécutifs de chaque fournisseur,
 * pour l'écran Santé & Observabilité et l'alerte Telegram.
 *
 * Un fournisseur n'est tenu pour défaillant qu'après plusieurs échecs de
 * suite : un appel isolé qui échoue n'est pas une panne.
 */
class RoutingHealthService
{
    public const YANDEX = 'yandex';

    public const OSRM = 'osrm';

    /** Échecs consécutifs à partir desquels un fournisseur est tenu pour défaillant. */
    public const FAILURE_THRESHOLD = 3;

    private const LABELS = [
        self::YANDEX => 'Yandex Distance Matrix',
        self::OSRM => 'OSRM',
    ];

    public function recordSuccess(string $provider): void
    {
        $state = $this->state($provider);

        // Aucune écriture tant que tout va bien : ce chemin est celui de
        // chaque estimation de course.
        if ($state['consecutive_failures'] === 0 && $state['last_success_at'] !== null
            && now()->diffInMinutes($state['last_success_at'], true) < 10) {
            return;
        }

        $this->store($provider, [
            'consecutive_failures' => 0,
            'last_success_at' => now()->toIso8601String(),
        ] + $state);
    }

    /**
     * @param  string  $reason  Motif court, sans clé ni réponse brute du fournisseur.
     */
    public function recordFailure(string $provider, string $reason): void
    {
        $state = $this->state($provider);

        $this->store($provider, [
            'consecutive_failures' => $state['consecutive_failures'] + 1,
            'last_failure_at' => now()->toIso8601String(),
            'last_failure_reason' => $reason,
        ] + $state);
    }

    public function isFailing(string $provider): bool
    {
        return $this->state($provider)['consecutive_failures'] >= self::FAILURE_THRESHOLD;
    }

    /**
     * Les courses ne sont plus tarifées par le fournisseur officiel.
     */
    public function isDegraded(): bool
    {
        return $this->isFailing(self::YANDEX);
    }

    /**
     * @return array{degraded: bool, mode: string, providers: list<array<string, mixed>>}
     */
    public function snapshot(): array
    {
        $yandexDown = $this->isFailing(self::YANDEX);
        $osrmDown = $this->isFailing(self::OSRM);

        $mode = match (true) {
            ! $yandexDown => 'Les courses sont tarifées par Yandex.',
            ! $osrmDown => 'Yandex ne répond plus : les courses sont tarifées par le serveur OSRM.',
            default => 'Ni Yandex ni OSRM ne répondent : les courses sont tarifées sur une distance estimée.',
        };

        return [
            'degraded' => $yandexDown,
            'mode' => $mode,
            'osrm_public' => str_contains((string) config('services.osrm.base_url'), 'router.project-osrm.org'),
            'providers' => array_map(fn (string $provider) => [
                'key' => $provider,
                'label' => self::LABELS[$provider],
                'failing' => $this->isFailing($provider),
            ] + $this->state($provider), [self::YANDEX, self::OSRM]),
        ];
    }

    /**
     * @return array{consecutive_failures: int, last_success_at: ?string, last_failure_at: ?string, last_failure_reason: ?string}
     */
    private function state(string $provider): array
    {
        $stored = Cache::get($this->key($provider));

        return [
            'consecutive_failures' => (int) ($stored['consecutive_failures'] ?? 0),
            'last_success_at' => $stored['last_success_at'] ?? null,
            'last_failure_at' => $stored['last_failure_at'] ?? null,
            'last_failure_reason' => $stored['last_failure_reason'] ?? null,
        ];
    }

    private function store(string $provider, array $state): void
    {
        Cache::forever($this->key($provider), $state);
    }

    private function key(string $provider): string
    {
        return "routing_health:{$provider}";
    }
}
