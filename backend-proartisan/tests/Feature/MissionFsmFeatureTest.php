<?php

/**
 * Tests Pest – FEATURE : persistance et idempotence de la FSM Mission
 *
 * Valide les transitions réelles avec persistance Eloquent et la gestion de l'idempotence.
 */

use App\Models\Mission;
use App\Models\User;
use App\States\Mission\CompletedState;
use App\States\Mission\DisputedState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\PendingApprovalState;
use App\States\Mission\PendingFundingState;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function () {
    $this->client = User::factory()->create(['role' => 'client']);
    $this->artisan = User::factory()->create(['role' => 'artisan']);
});

// ══════════════════════════════════════════════════════════
//  Transitions avec persistance
// ══════════════════════════════════════════════════════════

describe('Persistance des transitions FSM', function () {

    it('persiste la transition de draft à pending_funding', function () {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Test FSM Transition',
            'status' => DraftState::class,
        ]);

        expect($mission->status)->toBeInstanceOf(DraftState::class);

        $mission->status->transitionTo(PendingFundingState::class);
        $mission->save();

        expect($mission->fresh()->status)->toBeInstanceOf(PendingFundingState::class);
    });

    it('rejette les transitions invalides et lève une exception Spatie', function () {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Test FSM Rejection',
            'status' => DraftState::class,
        ]);

        expect(fn() => $mission->status->transitionTo(InProgressState::class))
            ->toThrow(TransitionNotFound::class);
    });

});

// ══════════════════════════════════════════════════════════
//  Idempotence des transitions
// ══════════════════════════════════════════════════════════

describe('Idempotence des transitions', function () {

    it('ne fait rien (idempotence) si l\'on transitionne vers le même état', function () {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Test Idempotence',
            'status' => FundedLockedState::class,
        ]);

        $mission->status->transitionTo(FundedLockedState::class);
        $mission->save();

        expect($mission->fresh()->status)->toBeInstanceOf(FundedLockedState::class);
    });

});

// ══════════════════════════════════════════════════════════
//  Guards et Cohérence métier du modèle Mission
// ══════════════════════════════════════════════════════════

describe('Guards de l\'état Mission', function () {

    it('valide isFundsFrozen selon les états', function () {
        $mission = new Mission(['status' => DraftState::class, 'funds_frozen' => false]);
        expect($mission->isFundsFrozen())->toBeFalse();

        $mission->funds_frozen = true;
        expect($mission->isFundsFrozen())->toBeTrue();

        $mission->status = DisputedState::class;
        $mission->funds_frozen = false;
        expect($mission->isFundsFrozen())->toBeTrue(); // true car DisputedState
    });

    it('valide canGenerateJCode', function () {
        $mission = new Mission(['status' => DraftState::class]);
        expect($mission->canGenerateJCode())->toBeFalse();

        $mission->status = FundedLockedState::class;
        expect($mission->canGenerateJCode())->toBeTrue();

        $mission->status = InProgressState::class;
        expect($mission->canGenerateJCode())->toBeTrue();

        $mission->status = CompletedState::class;
        expect($mission->canGenerateJCode())->toBeFalse();
    });

    it('valide canSubmitJalon', function () {
        $mission = new Mission(['status' => FundedLockedState::class]);
        expect($mission->canSubmitJalon())->toBeFalse();

        $mission->status = InProgressState::class;
        expect($mission->canSubmitJalon())->toBeTrue();
    });

});

