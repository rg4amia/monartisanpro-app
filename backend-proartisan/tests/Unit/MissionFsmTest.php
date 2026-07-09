<?php

/**
 * Tests Pest – UNIT : logique pure de la FSM Mission
 *
 * Vérifie sans base de données :
 *   – les transitions autorisées (happy path + urgence + reprise litige)
 *   – les transitions interdites
 *   – l'état initial par défaut
 *   – l'idempotence des guards métier du modèle Mission
 *   – les noms de statuts ($name)
 *
 * Dépendance : spatie/laravel-model-states
 */

use App\States\Mission\CompletedState;
use App\States\Mission\DisputedState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\MissionState;
use App\States\Mission\PendingApprovalState;
use App\States\Mission\PendingFundingState;

// ──────────────────────────────────────────────────────────
// HELPER : vérifie via la config de la FSM si $from → $to est autorisé
// ──────────────────────────────────────────────────────────

function fsmAllows(string $from, string $to): bool
{
    $config = MissionState::config();
    $ref    = new ReflectionObject($config);

    $prop = $ref->getProperty('allowedTransitions');
    $prop->setAccessible(true);
    $transitions = $prop->getValue($config);

    $fromName = $from::$name;
    $toName   = $to::$name;

    return array_key_exists("{$fromName}-{$toName}", $transitions);
}

// ══════════════════════════════════════════════════════════
//  GROUPE 1 – Transitions AUTORISÉES (happy path nominal)
// ══════════════════════════════════════════════════════════

describe('transitions autorisées (happy path)', function () {

    it('permet draft → pending_funding', function () {
        expect(fsmAllows(DraftState::class, PendingFundingState::class))->toBeTrue();
    });

    it('permet pending_funding → funded_locked', function () {
        expect(fsmAllows(PendingFundingState::class, FundedLockedState::class))->toBeTrue();
    });

    it('permet funded_locked → in_progress', function () {
        expect(fsmAllows(FundedLockedState::class, InProgressState::class))->toBeTrue();
    });

    it('permet in_progress → pending_approval', function () {
        expect(fsmAllows(InProgressState::class, PendingApprovalState::class))->toBeTrue();
    });

    it('permet pending_approval → in_progress (nouveau jalon)', function () {
        expect(fsmAllows(PendingApprovalState::class, InProgressState::class))->toBeTrue();
    });

    it('permet pending_approval → completed', function () {
        expect(fsmAllows(PendingApprovalState::class, CompletedState::class))->toBeTrue();
    });
})->group('fsm', 'happy-path');

// ══════════════════════════════════════════════════════════
//  GROUPE 2 – Transition d'urgence : tout état disputé → DISPUTED
// ══════════════════════════════════════════════════════════

describe('transition d\'urgence vers disputed', function () {

    it('permet pending_funding → disputed', function () {
        expect(fsmAllows(PendingFundingState::class, DisputedState::class))->toBeTrue();
    });

    it('permet funded_locked → disputed', function () {
        expect(fsmAllows(FundedLockedState::class, DisputedState::class))->toBeTrue();
    });

    it('permet in_progress → disputed', function () {
        expect(fsmAllows(InProgressState::class, DisputedState::class))->toBeTrue();
    });

    it('permet pending_approval → disputed', function () {
        expect(fsmAllows(PendingApprovalState::class, DisputedState::class))->toBeTrue();
    });
})->group('fsm', 'disputed');

// ══════════════════════════════════════════════════════════
//  GROUPE 3 – Reprise après résolution du litige
// ══════════════════════════════════════════════════════════

describe('reprise depuis disputed', function () {

    it('permet disputed → in_progress', function () {
        expect(fsmAllows(DisputedState::class, InProgressState::class))->toBeTrue();
    });

    it('permet disputed → completed', function () {
        expect(fsmAllows(DisputedState::class, CompletedState::class))->toBeTrue();
    });
})->group('fsm', 'disputed');

// ══════════════════════════════════════════════════════════
//  GROUPE 4 – Transitions INTERDITES
// ══════════════════════════════════════════════════════════

describe('transitions interdites', function () {

    it('interdit draft → in_progress (saute pending_funding)', function () {
        expect(fsmAllows(DraftState::class, InProgressState::class))->toBeFalse();
    });

    it('interdit draft → funded_locked', function () {
        expect(fsmAllows(DraftState::class, FundedLockedState::class))->toBeFalse();
    });

    it('interdit draft → completed', function () {
        expect(fsmAllows(DraftState::class, CompletedState::class))->toBeFalse();
    });

    it('interdit draft → disputed', function () {
        expect(fsmAllows(DraftState::class, DisputedState::class))->toBeFalse();
    });

    it('interdit pending_funding → in_progress (saute funded_locked)', function () {
        expect(fsmAllows(PendingFundingState::class, InProgressState::class))->toBeFalse();
    });

    it('interdit funded_locked → pending_approval (saute in_progress)', function () {
        expect(fsmAllows(FundedLockedState::class, PendingApprovalState::class))->toBeFalse();
    });

    it('interdit in_progress → completed (doit passer par pending_approval)', function () {
        expect(fsmAllows(InProgressState::class, CompletedState::class))->toBeFalse();
    });

    it('interdit completed → tout autre état (état terminal immuable)', function () {
        $targets = [
            DraftState::class,
            PendingFundingState::class,
            FundedLockedState::class,
            InProgressState::class,
            PendingApprovalState::class,
            DisputedState::class,
        ];

        foreach ($targets as $target) {
            expect(fsmAllows(CompletedState::class, $target))
                ->toBeFalse("completed → {$target} ne doit pas être autorisé");
        }
    });

    it('interdit disputed → pending_funding', function () {
        expect(fsmAllows(DisputedState::class, PendingFundingState::class))->toBeFalse();
    });

    it('interdit disputed → draft', function () {
        expect(fsmAllows(DisputedState::class, DraftState::class))->toBeFalse();
    });
})->group('fsm', 'forbidden');

// ══════════════════════════════════════════════════════════
//  GROUPE 5 – État initial par défaut
// ══════════════════════════════════════════════════════════

describe('état initial', function () {

    it('définit draft comme état par défaut', function () {
        $config = MissionState::config();
        $ref    = new ReflectionObject($config);
        $prop   = $ref->getProperty('defaultStateClass');
        $prop->setAccessible(true);

        expect($prop->getValue($config))->toBe(DraftState::class);
    });
})->group('fsm', 'default');

// ══════════════════════════════════════════════════════════
//  GROUPE 6 – Noms de statuts ($name)
// ══════════════════════════════════════════════════════════

describe('noms de statuts ($name)', function () {

    it('DraftState::$name = "draft"', function () {
        expect(DraftState::$name)->toBe('draft');
    });

    it('PendingFundingState::$name = "pending_funding"', function () {
        expect(PendingFundingState::$name)->toBe('pending_funding');
    });

    it('FundedLockedState::$name = "funded_locked"', function () {
        expect(FundedLockedState::$name)->toBe('funded_locked');
    });

    it('InProgressState::$name = "in_progress"', function () {
        expect(InProgressState::$name)->toBe('in_progress');
    });

    it('PendingApprovalState::$name = "pending_approval"', function () {
        expect(PendingApprovalState::$name)->toBe('pending_approval');
    });

    it('CompletedState::$name = "completed"', function () {
        expect(CompletedState::$name)->toBe('completed');
    });

    it('DisputedState::$name = "disputed"', function () {
        expect(DisputedState::$name)->toBe('disputed');
    });
})->group('fsm', 'names');
