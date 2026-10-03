<?php

use App\Models\Evaluation;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Order;
use App\Models\ScoreLedgerEntry;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\AdminService;
use App\Services\EvaluationService;
use App\Services\ScoreService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * Chantier 25 — corrections du module « Évaluations & Scores » relevées par
 * l'analyse du 03/10/2026 (aucune ne change la règle de calcul du score).
 */

function c25Client(): User
{
    return User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
}

function c25Artisan(array $attributes = []): User
{
    return User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif'] + $attributes);
}

function c25Mission(User $client, User $artisan, string $status = 'completed'): Mission
{
    return Mission::create([
        'client_id' => $client->id,
        'artisan_id' => $artisan->id,
        'description' => 'Réfection de toiture',
        'status' => $status,
        'montant_total' => 100000,
        'montant_materiaux' => 60000,
        'montant_mo' => 40000,
        'ratio_materiaux' => 0.60,
    ]);
}

function c25Order(User $client, User $supplier, ?User $driver, string $status = 'delivered'): Order
{
    return Order::create([
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'driver_id' => $driver?->id,
        'status' => $status,
        'subtotal' => 25000,
        'delivery_cost' => 2000,
        'platform_fee' => 0,
        'total_amount' => 27000,
        'pickup_code' => '4821',
        'reception_code' => '7734',
        'delivery_mode' => 'delivery',
    ]);
}

// ── Données exposées ────────────────────────────────────────────────────────

test('« mes évaluations » ne transmet de l\'autre partie que son identifiant, son nom et son rôle', function () {
    $client = c25Client();
    $artisan = c25Artisan(['phone' => '+2250700000099']);
    $artisan->setPosition(5.35, -4.01);
    $mission = c25Mission($client, $artisan);

    $this->actingAs($client, 'sanctum')
        ->postJson('/api/v1/evaluations', ['mission_id' => $mission->id, 'evalue_id' => $artisan->id, 'note' => 5])
        ->assertCreated();

    $given = $this->actingAs($client, 'sanctum')->getJson('/api/v1/evaluations/my')->assertOk()->json('data.given.0');

    expect(array_keys($given['evalue']))->toBe(['id', 'name', 'role'])
        ->and($given['mission_id'])->toBe($mission->id)
        ->and($given['note'])->toBe(5)
        ->and($given)->not->toHaveKeys(['mission', 'order', 'evaluateur']);

    $received = $this->actingAs($artisan, 'sanctum')->getJson('/api/v1/evaluations/my')->assertOk()->json('data.received.0');

    expect(array_keys($received['evaluateur']))->toBe(['id', 'name', 'role']);

    $raw = json_encode([$given, $received]);
    expect($raw)->not->toContain('+2250700000099')
        ->not->toContain('coordinates')
        ->not->toContain('wallet_mo')
        ->not->toContain('payment_phone');
});

// ── Qui peut être évalué ────────────────────────────────────────────────────

test('un livreur sans lien avec la mission ne peut pas être évalué depuis cette mission', function () {
    $client = c25Client();
    $mission = c25Mission($client, c25Artisan());
    $livreur = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

    $this->actingAs($client, 'sanctum')
        ->postJson('/api/v1/evaluations', ['mission_id' => $mission->id, 'evalue_id' => $livreur->id, 'note' => 1])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Evaluation::count())->toBe(0)
        ->and($livreur->fresh()->score_prosartisan)->toBe(0);
});

test('le livreur de la commande s\'évalue depuis la commande, un autre livreur non', function () {
    $client = c25Client();
    $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    $livreur = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    $autre = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
    $order = c25Order($client, $supplier, $livreur);

    $this->actingAs($client, 'sanctum')
        ->postJson('/api/v1/evaluations', ['order_id' => $order->id, 'evalue_id' => $autre->id, 'note' => 5])
        ->assertStatus(422);

    $this->actingAs($client, 'sanctum')
        ->postJson('/api/v1/evaluations', ['order_id' => $order->id, 'evalue_id' => $livreur->id, 'note' => 5])
        ->assertCreated();
});

test('la commande d\'un autre client répond 403 sans révéler son état', function () {
    $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    $order = c25Order(c25Client(), $supplier, null, 'paid');

    $this->actingAs(c25Client(), 'sanctum')
        ->postJson('/api/v1/evaluations', ['order_id' => $order->id, 'evalue_id' => $supplier->id, 'note' => 5])
        ->assertStatus(403);
});

// ── Doublons ────────────────────────────────────────────────────────────────

test('la base refuse deux évaluations de la même personne pour la même mission', function () {
    $client = c25Client();
    $artisan = c25Artisan();
    $mission = c25Mission($client, $artisan);
    $row = ['mission_id' => $mission->id, 'evaluateur_id' => $client->id, 'evalue_id' => $artisan->id, 'note' => 5];

    Evaluation::create($row);

    expect(fn () => Evaluation::create($row))->toThrow(UniqueConstraintViolationException::class);
});

test('la base refuse deux évaluations de la même personne pour la même commande, mais en accepte sur deux commandes', function () {
    $client = c25Client();
    $supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    $first = c25Order($client, $supplier, null);
    $second = c25Order($client, $supplier, null);

    Evaluation::create(['order_id' => $first->id, 'evaluateur_id' => $client->id, 'evalue_id' => $supplier->id, 'note' => 4]);
    Evaluation::create(['order_id' => $second->id, 'evaluateur_id' => $client->id, 'evalue_id' => $supplier->id, 'note' => 4]);

    expect(fn () => Evaluation::create(['order_id' => $first->id, 'evaluateur_id' => $client->id, 'evalue_id' => $supplier->id, 'note' => 2]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('une seconde évaluation de la même personne répond 422 avec un message clair', function () {
    $client = c25Client();
    $artisan = c25Artisan();
    $mission = c25Mission($client, $artisan);
    $payload = ['mission_id' => $mission->id, 'evalue_id' => $artisan->id, 'note' => 5];

    $this->actingAs($client, 'sanctum')->postJson('/api/v1/evaluations', $payload)->assertCreated();
    $this->actingAs($client, 'sanctum')->postJson('/api/v1/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Vous avez déjà évalué cette personne pour cette mission.');
});

// ── Erreur interne ──────────────────────────────────────────────────────────

test('une erreur interne ne renvoie jamais le message technique à l\'utilisateur', function () {
    $client = c25Client();
    $artisan = c25Artisan();
    $mission = c25Mission($client, $artisan);

    $this->mock(EvaluationService::class, function ($mock) {
        $mock->shouldReceive('create')->andThrow(new RuntimeException('SQLSTATE[HY000] table evaluations is locked'));
    });

    $message = $this->actingAs($client, 'sanctum')
        ->postJson('/api/v1/evaluations', ['mission_id' => $mission->id, 'evalue_id' => $artisan->id, 'note' => 5])
        ->assertStatus(500)
        ->json('message');

    expect($message)->not->toContain('SQLSTATE')->toContain("n'a pas pu être enregistrée");
});

// ── Crédibilité ─────────────────────────────────────────────────────────────

test('un client aux quatre missions terminées pèse pleinement, un client neuf non', function () {
    $service = app(ScoreService::class);
    $fidele = c25Client();
    $artisan = c25Artisan();

    foreach (range(1, 4) as $i) {
        c25Mission($fidele, $artisan);
    }

    $neuf = c25Client();
    c25Mission($neuf, $artisan);

    expect($service->resolveCredibility($fidele))->toBe(1.0)
        ->and($service->resolveCredibility($neuf))->toBe(0.1)
        ->and($service->resolveCredibility(null))->toBe(0.1);
});

test('le bonus d\'une évaluation porte sur cette évaluation et n\'est inscrit qu\'une fois', function () {
    $client = c25Client();
    $artisan = c25Artisan();
    $service = app(ScoreService::class);

    $bonne = Evaluation::create(['mission_id' => c25Mission($client, $artisan)->id, 'evaluateur_id' => $client->id, 'evalue_id' => $artisan->id, 'note' => 5, 'fiabilite' => 5, 'integrite' => 5, 'qualite' => 5, 'reactivite' => 5]);
    // Une évaluation plus récente et défavorable existe déjà quand le bonus de la première est calculé.
    Evaluation::create(['mission_id' => c25Mission($client, $artisan)->id, 'evaluateur_id' => $client->id, 'evalue_id' => $artisan->id, 'note' => 1, 'fiabilite' => 1, 'integrite' => 1, 'qualite' => 1, 'reactivite' => 1]);

    $service->recalculate($artisan, $bonne);
    $service->recalculate($artisan, $bonne);

    $entries = ScoreLedgerEntry::where('user_id', $artisan->id)->get();
    expect($entries)->toHaveCount(1)
        ->and($entries->first()->evaluation_id)->toBe($bonne->id)
        ->and($entries->first()->event_type)->toBe('success_mission');
});

// ── Dégradation d'inactivité ────────────────────────────────────────────────

function c25ArtisanNote(int $score = 1000): User
{
    $artisan = c25Artisan();
    // Dix clients distincts : la maturité ne compte pas les évaluations d'un même client.
    foreach (range(1, 10) as $i) {
        Evaluation::create(['evaluateur_id' => c25Client()->id, 'evalue_id' => $artisan->id, 'note' => 5, 'fiabilite' => 5, 'integrite' => 5, 'qualite' => 5, 'reactivite' => 5]);
    }
    app(ScoreService::class)->recalculateFromLedger($artisan);

    return $artisan->fresh();
}

test('les jours d\'inactivité se comptent depuis la dernière étape ou la dernière mission, sans colonne inexistante', function () {
    $service = app(ScoreService::class);
    $artisan = c25Artisan();

    $this->travel(20)->days();
    expect($service->getInactivityDays($artisan->fresh()))->toBe(20);

    $mission = c25Mission(c25Client(), $artisan, 'in_progress');
    $this->travel(5)->days();
    expect($service->getInactivityDays($artisan->fresh()))->toBe(5);

    Jalon::create(['mission_id' => $mission->id, 'ordre' => 1, 'description' => 'Étape 1', 'montant' => 10000, 'statut' => 'valide', 'date_prevue' => now()->toDateString()]);
    $this->travel(2)->days();
    expect($service->getInactivityDays($artisan->fresh()))->toBe(2);
});

test('la dégradation retire 5 points par semaine, quel que soit le nombre de passages', function () {
    config(['prosartisan.score_prosartisan.inactivity_decay_enabled' => true]);
    $artisan = c25ArtisanNote();
    expect($artisan->score_prosartisan)->toBe(1000);

    $this->travel(60)->days();

    // 21 passages quotidiens : trois semaines, donc trois pénalités.
    foreach (range(1, 21) as $day) {
        $this->artisan('prosartisan:decay-score')->assertExitCode(0);
        $this->travel(1)->days();
    }

    expect((int) ScoreLedgerEntry::where('user_id', $artisan->id)->where('event_type', 'inactivity_decay')->sum('points'))->toBe(-15)
        ->and($artisan->fresh()->score_prosartisan)->toBe(985);
});

test('la dégradation est éteinte par défaut : la commande ne modifie aucun score', function () {
    $artisan = c25ArtisanNote();
    $this->travel(120)->days();

    $this->artisan('prosartisan:decay-score')
        ->expectsOutputToContain('désactivée')
        ->assertExitCode(0);

    expect(ScoreLedgerEntry::where('event_type', 'inactivity_decay')->count())->toBe(0)
        ->and($artisan->fresh()->score_prosartisan)->toBe(1000);
});

test('un artisan actif, gelé ou déjà à zéro n\'est pas pénalisé', function () {
    config(['prosartisan.score_prosartisan.inactivity_decay_enabled' => true]);
    $service = app(ScoreService::class);

    $gele = c25ArtisanNote();
    $gele->update(['score_frozen' => true]);
    $zero = c25Artisan();
    $actif = c25ArtisanNote();

    $this->travel(90)->days();
    c25Mission(c25Client(), $actif, 'in_progress');

    expect($service->applyInactivityDecay($gele->fresh()))->toBe(0)
        ->and($service->applyInactivityDecay($zero->fresh()))->toBe(0)
        ->and($service->applyInactivityDecay($actif->fresh()))->toBe(0)
        ->and(ScoreLedgerEntry::where('event_type', 'inactivity_decay')->count())->toBe(0);
});

// ── Backoffice ──────────────────────────────────────────────────────────────

test('la recherche du classement ne renvoie que des artisans', function () {
    $client = c25Client();
    $artisan = c25Artisan(['name' => 'Koffi Maçon']);

    $admin = app(AdminService::class);

    // L'identifiant du client peut aussi figurer dans le téléphone d'un artisan :
    // seul compte le fait qu'aucun compte non artisan ne remonte.
    expect($admin->paginateArtisanScores((string) $client->id)->getCollection()->where('role', '!=', 'artisan')->count())->toBe(0)
        ->and($admin->paginateArtisanScores($client->phone)->total())->toBe(0)
        ->and($admin->paginateArtisanScores('Koffi')->getCollection()->pluck('id')->all())->toBe([$artisan->id]);
});

test('la note moyenne vaut null tant qu\'aucune évaluation n\'existe', function () {
    expect(app(AdminService::class)->evaluationStats()['note_moyenne'])->toBeNull();

    Evaluation::create(['evaluateur_id' => c25Client()->id, 'evalue_id' => c25Artisan()->id, 'note' => 4]);

    expect(app(AdminService::class)->evaluationStats()['note_moyenne'])->toBe(4.0);
});

test('la fiche d\'un artisan charge tout son historique de score, avec des libellés français', function () {
    $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    $artisan = c25Artisan();
    $autre = c25Artisan();

    ScoreLedgerEntry::create(['user_id' => $artisan->id, 'event_type' => 'jcode_scan_success', 'points' => 5, 'credibility_factor' => 1, 'description' => 'Bon PA-1']);
    // 150 lignes plus récentes d'un autre compte : l'ancienne liste des 100 dernières masquait l'artisan.
    DB::table('score_ledger_entries')->insert(array_map(fn ($i) => [
        'user_id' => $autre->id, 'event_type' => 'success_mission', 'points' => 5, 'credibility_factor' => 1,
        'description' => "Ligne {$i}", 'created_at' => now()->addMinutes($i), 'updated_at' => now(),
    ], range(1, 150)));

    $this->actingAs($admin)->getJson("/admin/users/{$artisan->id}/score-ledger")
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('entries.0.event_label', 'Bon matériel validé')
        ->assertJsonPath('entries.0.points', 5);

    $restreint = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    app(AdminPermissionService::class)->sync($restreint, ['admin.users.view'], $admin);
    $this->actingAs($restreint)->getJson("/admin/users/{$artisan->id}/score-ledger")->assertForbidden();
});
