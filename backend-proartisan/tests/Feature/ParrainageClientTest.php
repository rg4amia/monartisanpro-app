<?php

use App\Models\CampagneParrainage;
use App\Models\Devis;
use App\Models\Mission;
use App\Models\ParrainageClient;
use App\Models\PromoCode;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuthService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un artisan ne peut pas parrainer un autre client', function () {
    $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700000001']);
    $filleul = User::factory()->create(['role' => 'client', 'phone' => '+2250700000002']);

    $response = $this->actingAs($artisan)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Seuls les clients peuvent parrainer d\'autres clients.');

    $this->assertDatabaseMissing('parrainages_clients', ['filleul_id' => $filleul->id]);
});

test('un client ne peut pas parrainer un artisan', function () {
    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000003']);
    $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700000004']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => $artisan->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Le filleul doit avoir le rôle client.');
});

test('un client ne peut pas se parrainer lui-meme', function () {
    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000005']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => $parrain->phone,
        'filleul_nom' => 'Moi-même',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Vous ne pouvez pas vous parrainer vous-même.');
});

test('un client deja parraine ne peut pas etre parraine a nouveau', function () {
    $parrain1 = User::factory()->create(['role' => 'client', 'phone' => '+2250700000006']);
    $parrain2 = User::factory()->create(['role' => 'client', 'phone' => '+2250700000007']);
    $filleul = User::factory()->create(['role' => 'client', 'phone' => '+2250700000008']);

    ParrainageClient::create([
        'parrain_id' => $parrain1->id,
        'filleul_id' => $filleul->id,
        'filleul_phone' => $filleul->phone,
        'statut' => 'en_attente',
    ]);

    $response = $this->actingAs($parrain2)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Ce client a déjà un parrain.');
});

test('un client peut parrainer un autre client', function () {
    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000009']);
    $filleul = User::factory()->create(['role' => 'client', 'phone' => '+2250700000010']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => $filleul->phone,
        'filleul_nom' => 'Nom Ignoré',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('parrainages_clients', [
        'parrain_id' => $parrain->id,
        'filleul_id' => $filleul->id,
        'filleul_phone' => $filleul->phone,
        'statut' => 'en_attente',
    ]);
});

test('inviter un numero client non inscrit cree une ligne en attente et envoie un sms', function () {
    $smsMock = $this->mock(SmsService::class);
    $smsMock->shouldReceive('send')
        ->once()
        ->with('+2250700000030', Mockery::pattern('/vous invite à rejoindre ProsArtisan/'))
        ->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000029']);

    $response = $this->actingAs($parrain)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => '+2250700000030',
        'filleul_nom' => 'Futur Client',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('parrainages_clients', [
        'parrain_id' => $parrain->id,
        'filleul_id' => null,
        'filleul_phone' => '+2250700000030',
        'filleul_nom' => 'Futur Client',
        'statut' => 'en_attente_inscription',
    ]);
});

test('inscription ulterieure avec role client lie automatiquement le parrainage client en attente', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000031']);

    $this->actingAs($parrain)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => '+2250700000032',
        'filleul_nom' => 'Futur Client',
    ])->assertCreated();

    $filleul = app(AuthService::class)->findOrCreateByPhone('+2250700000032');

    app(AuthService::class)->register($filleul, ['name' => 'Nouveau Client', 'role' => 'client']);

    $parrainage = ParrainageClient::where('filleul_phone', '+2250700000032')->first();

    expect($parrainage->statut)->toBe('en_attente');
    expect($parrainage->filleul_id)->toBe($filleul->id);
});

test('inscription avec un mauvais role ne lie pas le parrainage client en attente', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000033']);

    $this->actingAs($parrain)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => '+2250700000034',
        'filleul_nom' => 'Futur Client',
    ])->assertCreated();

    $filleul = app(AuthService::class)->findOrCreateByPhone('+2250700000034');

    app(AuthService::class)->register($filleul, ['name' => 'Un Artisan', 'role' => 'artisan']);

    $parrainage = ParrainageClient::where('filleul_phone', '+2250700000034')->first();

    expect($parrainage->statut)->toBe('en_attente_inscription');
    expect($parrainage->filleul_id)->toBeNull();
});

test('double invitation client du meme numero renvoie une erreur claire sans exception sql', function () {
    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['status' => 'success']);

    $parrain1 = User::factory()->create(['role' => 'client', 'phone' => '+2250700000035']);
    $parrain2 = User::factory()->create(['role' => 'client', 'phone' => '+2250700000036']);

    $this->actingAs($parrain1)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => '+2250700000037',
        'filleul_nom' => 'Futur Client',
    ])->assertCreated();

    $response = $this->actingAs($parrain2)->postJson('/api/v1/parrainages-clients', [
        'filleul_phone' => '+2250700000037',
        'filleul_nom' => 'Futur Client Bis',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Ce client a déjà un parrain.');
});

test('aucune recompense sans campagne active quand la mission du filleul est financee', function () {
    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000011']);
    $filleul = User::factory()->create(['role' => 'client', 'phone' => '+2250700000012']);
    $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700000013']);

    ParrainageClient::create([
        'parrain_id' => $parrain->id,
        'filleul_id' => $filleul->id,
        'statut' => 'en_attente',
    ]);

    $mission = Mission::create([
        'client_id' => $filleul->id,
        'artisan_id' => $artisan->id,
        'description' => 'Réparation plomberie',
        'status' => 'pending_funding',
    ]);

    // Aucune campagne active en base : la récompense ne doit pas se déclencher.
    // (le seed de la migration `promo_codes` insère déjà un code de bienvenue
    // générique — non lié au parrainage — d'où la comparaison par propriétaire
    // plutôt qu'un total à zéro.)
    $mission->update(['status' => 'funded_locked']);

    $parrainage = ParrainageClient::where('filleul_id', $filleul->id)->first();

    expect($parrainage->statut)->toBe('en_attente');
    expect($parrainage->promo_code_id)->toBeNull();
    $this->assertDatabaseMissing('promo_codes', ['owner_user_id' => $parrain->id]);
});

test('la recompense de parrainage cree un code promo quand une campagne est active', function () {
    $campagne = CampagneParrainage::create([
        'libelle' => 'Campagne de lancement',
        'discount_type' => 'percent',
        'discount_value' => 15,
        'min_montant' => 0,
        'is_active' => true,
    ]);

    $parrain = User::factory()->create(['role' => 'client', 'phone' => '+2250700000014']);
    $filleul = User::factory()->create(['role' => 'client', 'phone' => '+2250700000015']);
    $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700000016']);

    ParrainageClient::create([
        'parrain_id' => $parrain->id,
        'filleul_id' => $filleul->id,
        'statut' => 'en_attente',
    ]);

    $mission = Mission::create([
        'client_id' => $filleul->id,
        'artisan_id' => $artisan->id,
        'description' => 'Réparation plomberie',
        'status' => 'pending_funding',
    ]);

    // Déclenche MissionFundedObserver -> ParrainageClientService::recompenserSiEligible().
    $mission->update(['status' => 'funded_locked']);

    $parrainage = ParrainageClient::where('filleul_id', $filleul->id)->first();

    expect($parrainage->statut)->toBe('recompense');
    expect($parrainage->campagne_id)->toBe($campagne->id);
    expect($parrainage->promo_code_id)->not->toBeNull();
    expect($parrainage->recompense_at)->not->toBeNull();

    $promoCode = PromoCode::find($parrainage->promo_code_id);

    expect($promoCode)->not->toBeNull();
    expect($promoCode->owner_user_id)->toBe($parrain->id);
    expect($promoCode->discount_type)->toBe('percent');
    expect($promoCode->discount_value)->toBe(15);
    expect($promoCode->usage_limit)->toBe(1);
    expect($promoCode->is_active)->toBeTrue();
    expect(str_starts_with($promoCode->code, 'PARR-'))->toBeTrue();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $parrain->id,
        'type' => 'referral_reward',
    ]);
});

test('un code promo appartenant a un autre client n\'est pas applique lors du paiement d\'acompte', function () {
    $owner = User::factory()->create(['role' => 'client', 'phone' => '+2250700000017']);
    $attacker = User::factory()->create(['role' => 'client', 'phone' => '+2250700000018', 'kyc_status' => 'actif']);
    $artisan = User::factory()->create(['role' => 'artisan', 'phone' => '+2250700000019', 'kyc_status' => 'actif']);

    $promoCode = PromoCode::create([
        'code' => 'PARR-OWNERTEST',
        'description' => 'Récompense de parrainage client',
        'discount_type' => 'percent',
        'discount_value' => 50,
        'min_order_amount' => 0,
        'usage_limit' => 1,
        'used_count' => 0,
        'is_active' => true,
        'owner_user_id' => $owner->id,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDays(30),
    ]);

    $mission = Mission::create([
        'client_id' => $attacker->id,
        'artisan_id' => $artisan->id,
        'description' => 'Réparation électrique',
        'status' => 'pending_funding',
    ]);

    $devis = Devis::create([
        'mission_id' => $mission->id,
        'artisan_id' => $artisan->id,
        'materials_required' => false,
        'commission_service_ratio' => 0,
        'lignes_json' => [
            ['type' => 'mo', 'description' => 'Main d\'œuvre', 'montant' => 100000],
        ],
        'jalons_json' => [
            ['ordre' => 1, 'description' => 'Jalon unique', 'montant' => 100000, 'date_cible' => now()->addDay()->toDateString()],
        ],
        'statut' => 'soumis',
    ]);

    // montant_total du devis (commission 0%) = 100 000 FCFA, sans matériaux.
    $response = $this->actingAs($attacker)->postJson('/api/v1/payments/initiate', [
        'mission_id' => $mission->id,
        'devis_id' => $devis->id,
        'montant' => 100000,
        'provider' => 'wave',
        'phone' => $attacker->phone,
        'promo_code' => $promoCode->code,
    ]);

    $response->assertOk();

    $transactionId = $response->json('data.transaction_id');
    $transaction = Transaction::find($transactionId);

    // Le montant facturé reste le montant plein : la réduction appartenant à
    // un autre utilisateur n'a PAS été appliquée (Règle d'or 36 : propriété
    // de la ressource, pas seulement le rôle de l'appelant).
    expect($transaction->montant)->toBe(100000);
    expect($transaction->metadata['discount_amount'] ?? null)->toBe(0);
    expect($transaction->metadata['promo_code'] ?? null)->toBeNull();

    $promoCode->refresh();
    expect($promoCode->used_count)->toBe(0);
});
