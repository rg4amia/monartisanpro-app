<?php

use App\Models\Evaluation;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Chantier 24 — formule du Score ProsArtisan : une étoile vaut 0 point, et le
 * plafond d'excellence porte sur le score total, bonus du ledger compris.
 */

function noter(User $evalue, int $fois, int $f, int $i, int $q, int $r): void
{
    // Un client par évaluation : la maturité compte les clients distincts.
    for ($n = 0; $n < $fois; $n++) {
        Evaluation::create([
            'evaluateur_id' => User::factory()->create(['role' => 'client', 'kyc_status' => 'actif'])->id,
            'evalue_id' => $evalue->id,
            'note' => (int) round(($f + $i + $q + $r) / 4),
            'fiabilite' => $f,
            'integrite' => $i,
            'qualite' => $q,
            'reactivite' => $r,
        ]);
    }
}

function bonus(User $user, int $points, int $fois = 1): void
{
    for ($n = 0; $n < $fois; $n++) {
        ScoreLedgerEntry::create([
            'user_id' => $user->id,
            'event_type' => 'success_mission',
            'points' => $points,
            'credibility_factor' => 1.00,
            'description' => 'Bonus de test',
        ]);
    }
}

function artisanNeuf(): User
{
    return User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
}

test('une étoile sur chaque critère ne rapporte aucun point', function () {
    $artisan = artisanNeuf();
    noter($artisan, 10, 1, 1, 1, 1);

    expect(app(ScoreService::class)->recalculateFromLedger($artisan))->toBe(0);
});

test('la conversion est linéaire de une à cinq étoiles', function (int $etoiles, int $attendu) {
    $artisan = artisanNeuf();
    noter($artisan, 10, $etoiles, $etoiles, $etoiles, $etoiles);

    expect(app(ScoreService::class)->recalculateFromLedger($artisan))->toBe($attendu);
})->with([
    'deux étoiles' => [2, 250],
    'trois étoiles' => [3, 500],
    'quatre étoiles' => [4, 750],
    'cinq étoiles' => [5, 1000],
]);

test('le détail du score donne les points de chaque pilier selon la même conversion', function () {
    $artisan = artisanNeuf();
    noter($artisan, 10, 5, 3, 1, 2);

    $detail = app(ScoreService::class)->getScoreDetail($artisan);

    expect($detail['breakdown_points'])->toBe(['fiabilite' => 400, 'integrite' => 150, 'qualite' => 0, 'reactivite' => 25])
        ->and($detail['max_points'])->toBe(['fiabilite' => 400, 'integrite' => 300, 'qualite' => 200, 'reactivite' => 100])
        ->and($detail['score_prosartisan'])->toBe(575)
        ->and($detail['excellence_threshold'])->toBe(800);
});

test('sans trois critères d\'excellence, les bonus ne font pas franchir le seuil', function () {
    $artisan = artisanNeuf();
    // Deux critères à 5 seulement : 400 + 300 + 150 + 75 = 925, plafonné.
    noter($artisan, 10, 5, 5, 4, 4);
    $service = app(ScoreService::class);

    expect($service->recalculateFromLedger($artisan))->toBe(800);

    bonus($artisan, 5, 20);

    expect($service->recalculateFromLedger($artisan))->toBe(800);
});

test('un compte sans évaluation ne dépasse pas le seuil par ses seuls bonus', function () {
    $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    bonus($fournisseur, 890);

    expect(app(ScoreService::class)->recalculateFromLedger($fournisseur))->toBe(800);
});

test('avec trois critères d\'excellence, le score dépasse le seuil et les bonus comptent', function () {
    $artisan = artisanNeuf();
    // Trois critères à 5, réactivité à 3 : 400 + 300 + 200 + 50 = 950.
    noter($artisan, 10, 5, 5, 5, 3);
    $service = app(ScoreService::class);

    expect($service->recalculateFromLedger($artisan))->toBe(950);

    bonus($artisan, 5, 4);

    expect($service->recalculateFromLedger($artisan))->toBe(970);
});

test('le seuil d\'excellence se lit dans la configuration', function () {
    config(['prosartisan.score_prosartisan.excellence_threshold' => 600]);
    $artisan = artisanNeuf();
    noter($artisan, 10, 5, 5, 4, 4);

    expect(app(ScoreService::class)->recalculateFromLedger($artisan))->toBe(600);
});

test('les pénalités s\'appliquent sous le plafond', function () {
    $artisan = artisanNeuf();
    noter($artisan, 10, 5, 5, 4, 4);
    bonus($artisan, -150);

    // 925 − 150 = 775 : sous le seuil, le plafond ne joue pas.
    expect(app(ScoreService::class)->recalculateFromLedger($artisan))->toBe(775);
});

test('le recalcul général met à jour les scores stockés et laisse les scores gelés', function () {
    $artisan = artisanNeuf();
    noter($artisan, 10, 1, 1, 1, 1);
    $artisan->update(['score_prosartisan' => 200]);

    $gele = artisanNeuf();
    noter($gele, 10, 1, 1, 1, 1);
    $gele->update(['score_prosartisan' => 200, 'score_frozen' => true]);

    $sansActivite = artisanNeuf();

    $bilan = app(ScoreService::class)->recalculateAll();

    expect($bilan)->toBe(['comptes' => 2, 'modifies' => 1])
        ->and($artisan->fresh()->score_prosartisan)->toBe(0)
        ->and($gele->fresh()->score_prosartisan)->toBe(200)
        ->and($sansActivite->fresh()->score_prosartisan)->toBe(0);
});
