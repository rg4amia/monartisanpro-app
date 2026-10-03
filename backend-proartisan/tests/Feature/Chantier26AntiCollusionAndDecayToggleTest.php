<?php

use App\Models\AdminActivityLog;
use App\Models\Evaluation;
use App\Models\ScoreLedgerEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\InactivityDecayAdminService;
use App\Services\AdminService;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Chantier 26 — la maturité du score compte les clients distincts (dix notes
 * d'un même client ne valent qu'un dixième du score potentiel), et la
 * dégradation d'inactivité se pilote depuis le backoffice.
 */

function c26Client(): User
{
    return User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
}

function c26Artisan(array $attributes = []): User
{
    return User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'account_status' => 'actif'] + $attributes);
}

function c26Admin(): User
{
    return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
}

function c26Noter(User $artisan, User $client, int $fois = 1, int $note = 5): void
{
    foreach (range(1, $fois) as $n) {
        Evaluation::create(['evaluateur_id' => $client->id, 'evalue_id' => $artisan->id, 'note' => $note, 'fiabilite' => $note, 'integrite' => $note, 'qualite' => $note, 'reactivite' => $note]);
    }
}

/** Artisan noté 5/5 par dix clients distincts : score 1000. */
function c26ArtisanAuMaximum(): User
{
    $artisan = c26Artisan();
    foreach (range(1, 10) as $n) {
        c26Noter($artisan, c26Client());
    }
    app(ScoreService::class)->recalculateFromLedger($artisan);

    return $artisan->fresh();
}

// ── Anti-collusion ──────────────────────────────────────────────────────────

test('dix évaluations à 5/5 d\'un seul client ne donnent qu\'un dixième du score', function () {
    $artisan = c26Artisan();
    c26Noter($artisan, c26Client(), 10);

    $service = app(ScoreService::class);
    $detail = $service->getScoreDetail($artisan);

    expect($service->recalculateFromLedger($artisan))->toBe(100)
        ->and($detail['total_evaluations'])->toBe(10)
        ->and($detail['distinct_clients'])->toBe(1)
        ->and($detail['maturity_missions_count'])->toBe(1)
        ->and($detail['maturity_percentage'])->toBe(10.0)
        ->and($detail['micro_credit_eligible'])->toBeFalse()
        ->and($detail['is_golden_marker'])->toBeFalse();
});

test('dix clients distincts à 5/5 donnent le score entier', function () {
    $artisan = c26ArtisanAuMaximum();

    expect($artisan->score_prosartisan)->toBe(1000)
        ->and(app(ScoreService::class)->getScoreDetail($artisan)['maturity_percentage'])->toBe(100.0);
});

test('les évaluations répétées d\'un client n\'ajoutent pas de maturité', function () {
    $artisan = c26Artisan();
    c26Noter($artisan, c26Client(), 8);
    c26Noter($artisan, c26Client());
    c26Noter($artisan, c26Client());

    // Dix évaluations, trois clients : 30 % du score potentiel.
    expect(app(ScoreService::class)->recalculateFromLedger($artisan))->toBe(300);
});

test('le nombre de clients exigé se lit dans la configuration', function () {
    config(['prosartisan.score_prosartisan.maturity_clients_target' => 5]);
    $artisan = c26Artisan();
    foreach (range(1, 5) as $n) {
        c26Noter($artisan, c26Client());
    }

    expect(app(ScoreService::class)->recalculateFromLedger($artisan))->toBe(1000);
});

test('le classement du backoffice donne le nombre de clients distincts', function () {
    $artisan = c26Artisan(['name' => 'Yao Plombier']);
    c26Noter($artisan, c26Client(), 4);
    c26Noter($artisan, c26Client());

    $ligne = app(AdminService::class)->paginateArtisanScores('Yao')->getCollection()->first();

    expect((int) $ligne->evaluations_recues_count)->toBe(5)
        ->and((int) $ligne->clients_distincts)->toBe(2);
});

// ── Dégradation d'inactivité : pilotage du backoffice ───────────────────────

test('sans réglage, la dégradation suit la configuration et l\'écran l\'annonce éteinte', function () {
    c26ArtisanAuMaximum();
    $this->travel(90)->days();

    $apercu = app(InactivityDecayAdminService::class)->overview();

    expect($apercu['enabled'])->toBeFalse()
        ->and($apercu['source'])->toBe('configuration')
        ->and($apercu['threshold_days'])->toBe(60)
        ->and($apercu['points'])->toBe(5)
        ->and($apercu['concerned'])->toBe(1)
        ->and($apercu['penalties_30d'])->toBe(0);
});

test('l\'administrateur active la dégradation : elle s\'applique et l\'action est auditée', function () {
    $artisan = c26ArtisanAuMaximum();
    $admin = c26Admin();
    $this->travel(90)->days();

    $this->actingAs($admin)
        ->from('/admin/evaluations')
        ->put('/admin/evaluations/inactivity-decay', ['enabled' => true])
        ->assertRedirect('/admin/evaluations')
        ->assertSessionHas('success');

    expect(ScoreService::inactivityDecayEnabled())->toBeTrue()
        ->and(Setting::where('key', ScoreService::INACTIVITY_DECAY_SETTING)->value('value'))->toBe('1');

    $log = AdminActivityLog::where('action', 'score.inactivity_decay.enabled')->first();
    expect($log)->not->toBeNull()
        ->and($log->admin_id)->toBe($admin->id)
        ->and($log->context['artisans_concernes'])->toBe(1)
        ->and($log->context['before'])->toBeFalse();

    $this->artisan('prosartisan:decay-score')->assertExitCode(0);

    $apercu = app(InactivityDecayAdminService::class)->overview();
    expect($artisan->fresh()->score_prosartisan)->toBe(995)
        ->and($apercu['enabled'])->toBeTrue()
        ->and($apercu['source'])->toBe('reglage')
        ->and($apercu['penalties_30d'])->toBe(1)
        ->and($apercu['points_removed_30d'])->toBe(5);
});

test('l\'administrateur désactive la dégradation : plus aucun point n\'est retiré', function () {
    $artisan = c26ArtisanAuMaximum();
    $admin = c26Admin();
    $this->travel(90)->days();

    $this->actingAs($admin)->put('/admin/evaluations/inactivity-decay', ['enabled' => true]);
    $this->actingAs($admin)->put('/admin/evaluations/inactivity-decay', ['enabled' => false])->assertSessionHas('success');

    $this->artisan('prosartisan:decay-score')->expectsOutputToContain('désactivée')->assertExitCode(0);

    expect($artisan->fresh()->score_prosartisan)->toBe(1000)
        ->and(ScoreLedgerEntry::where('event_type', 'inactivity_decay')->count())->toBe(0)
        ->and(AdminActivityLog::where('action', 'score.inactivity_decay.disabled')->count())->toBe(1);
});

test('le réglage du backoffice l\'emporte sur la configuration du serveur', function () {
    config(['prosartisan.score_prosartisan.inactivity_decay_enabled' => true]);
    expect(ScoreService::inactivityDecayEnabled())->toBeTrue();

    $this->actingAs(c26Admin())->put('/admin/evaluations/inactivity-decay', ['enabled' => false]);

    expect(ScoreService::inactivityDecayEnabled())->toBeFalse();
});

test('redemander l\'état en vigueur n\'ajoute pas de ligne d\'audit', function () {
    $admin = c26Admin();

    $this->actingAs($admin)->put('/admin/evaluations/inactivity-decay', ['enabled' => true]);
    $this->actingAs($admin)->put('/admin/evaluations/inactivity-decay', ['enabled' => true])->assertSessionHas('success');

    expect(AdminActivityLog::where('action', 'score.inactivity_decay.enabled')->count())->toBe(1);
});

test('une demande sans valeur est refusée', function () {
    $this->actingAs(c26Admin())
        ->put('/admin/evaluations/inactivity-decay', [])
        ->assertSessionHasErrors('enabled');

    expect(Setting::where('key', ScoreService::INACTIVITY_DECAY_SETTING)->exists())->toBeFalse();
});

test('sans la capacité des réglages, l\'administrateur ne peut pas activer la dégradation', function () {
    $lecteur = c26Admin();
    app(AdminPermissionService::class)->sync($lecteur, ['admin.evaluations.view'], c26Admin());

    $this->actingAs($lecteur)->put('/admin/evaluations/inactivity-decay', ['enabled' => true])->assertForbidden();
    $this->actingAs(c26Client())->put('/admin/evaluations/inactivity-decay', ['enabled' => true])->assertForbidden();

    expect(ScoreService::inactivityDecayEnabled())->toBeFalse();
});

test('l\'éditeur générique des réglages ne modifie pas la dégradation', function () {
    $admin = c26Admin();
    $this->actingAs($admin)->put('/admin/evaluations/inactivity-decay', ['enabled' => false]);
    $setting = Setting::where('key', ScoreService::INACTIVITY_DECAY_SETTING)->firstOrFail();

    $this->actingAs($admin)
        ->put("/admin/settings/{$setting->id}", ['value' => '1'])
        ->assertSessionHasErrors('value');

    expect(ScoreService::inactivityDecayEnabled())->toBeFalse();
});

test('seuls les artisans actifs, au score positif et non gelé, inactifs depuis 60 jours sont comptés', function () {
    c26ArtisanAuMaximum();
    c26ArtisanAuMaximum()->update(['score_frozen' => true]);
    c26ArtisanAuMaximum()->update(['account_status' => 'suspendu']);
    c26Artisan();

    $service = app(ScoreService::class);
    expect($service->countInactiveArtisans())->toBe(0);

    $this->travel(59)->days();
    expect($service->countInactiveArtisans())->toBe(0);

    $this->travel(1)->days();
    $recent = c26ArtisanAuMaximum();

    expect($service->countInactiveArtisans())->toBe(1)
        ->and($service->getInactivityDays($recent))->toBe(0);
});

test('l\'onglet des évaluations transmet l\'état de la dégradation', function () {
    $this->actingAs(c26Admin())
        ->get('/admin/evaluations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('inactivityDecay.enabled', false)
            ->where('inactivityDecay.threshold_days', 60)
            ->where('inactivityDecay.points', 5)
            ->has('inactivityDecay.concerned'));
});
