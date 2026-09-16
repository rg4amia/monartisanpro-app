<?php

namespace Tests\Unit;

use App\Exceptions\CircuitOpenException;
use App\Services\CircuitBreakerService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Garde-fou anti-cascade des paiements sortants Wave / Orange Money.
 * Aucun test n'existait jusqu'ici sur ce service, alors qu'il conditionne
 * directement si un virement fournisseur ou une libération de jalon
 * peut effectivement partir vers l'opérateur.
 */
class CircuitBreakerServiceTest extends TestCase
{
    private CircuitBreakerService $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = app(CircuitBreakerService::class);
    }

    public function test_circuit_is_closed_by_default(): void
    {
        $this->assertSame(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('wave'));
        $this->breaker->ensureAvailable('wave');
        $this->assertTrue(true);
    }

    public function test_circuit_opens_after_reaching_the_failure_threshold(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure('wave');
        }
        $this->assertSame(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('wave'));

        $this->breaker->recordFailure('wave');

        $this->assertSame(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('wave'));
    }

    public function test_open_circuit_blocks_calls_with_a_provider_message(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('orange_money');
        }

        $this->expectException(CircuitOpenException::class);
        $this->expectExceptionMessage('Orange Money');

        $this->breaker->ensureAvailable('orange_money');
    }

    public function test_a_success_resets_an_open_circuit_to_closed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('wave');
        }
        $this->assertSame(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('wave'));

        $this->breaker->recordSuccess('wave');

        $this->assertSame(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('wave'));
        $this->assertSame(0, $this->breaker->getFailureCount('wave'));
    }

    public function test_circuit_moves_to_half_open_once_cooldown_has_elapsed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('wave');
        }
        $this->assertSame(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('wave'));

        // Simule l'écoulement du cooldown de 30 s sans dépendre d'un vrai sleep().
        Cache::put('circuit_breaker:wave:opened_at', time() - 40, now()->addMinutes(10));

        $this->breaker->ensureAvailable('wave');

        $this->assertSame(CircuitBreakerService::STATE_HALF_OPEN, $this->breaker->getState('wave'));
    }

    public function test_a_failure_while_half_open_reopens_the_circuit_immediately(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('wave');
        }
        Cache::put('circuit_breaker:wave:opened_at', time() - 40, now()->addMinutes(10));
        $this->breaker->ensureAvailable('wave');
        $this->assertSame(CircuitBreakerService::STATE_HALF_OPEN, $this->breaker->getState('wave'));

        $this->breaker->recordFailure('wave');

        $this->assertSame(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('wave'));
    }

    public function test_a_success_while_half_open_closes_the_circuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('wave');
        }
        Cache::put('circuit_breaker:wave:opened_at', time() - 40, now()->addMinutes(10));
        $this->breaker->ensureAvailable('wave');
        $this->assertSame(CircuitBreakerService::STATE_HALF_OPEN, $this->breaker->getState('wave'));

        $this->breaker->recordSuccess('wave');

        $this->assertSame(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('wave'));
    }

    public function test_providers_have_independent_circuits(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('wave');
        }

        $this->assertSame(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('wave'));
        $this->assertSame(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('orange_money'));
        $this->breaker->ensureAvailable('orange_money');
    }

    public function test_manual_reset_forces_the_circuit_closed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('wave');
        }
        $this->assertSame(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('wave'));

        $this->breaker->reset('wave');

        $this->assertSame(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('wave'));
        $this->assertSame(0, $this->breaker->getFailureCount('wave'));
        $this->breaker->ensureAvailable('wave');
    }
}
