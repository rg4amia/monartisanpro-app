<?php

use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\User;
use App\Services\GoogleMapsService;
use App\Services\SmsService;
use Tests\Support\Geo;

beforeEach(function () {
    // La passerelle USSD/SMS doit s'authentifier : on configure un secret de
    // test et on l'envoie sur toutes les requêtes du fichier. Les tests qui
    // vérifient le refus le retirent explicitement.
    config(['services.gateway.secret' => 'secret-passerelle-test']);
    $this->withHeader('X-Gateway-Secret', 'secret-passerelle-test');

    $this->mock(GoogleMapsService::class, function ($mock) {
        $mock->shouldReceive('getDirections')->andReturn([
            'distance' => 5000,
            'duration' => 600,
            'source' => 'mocked',
        ]);
    });

    // Mock SmsService to prevent outbound API requests
    $this->smsMock = $this->mock(SmsService::class, function ($mock) {
        $mock->shouldReceive('normalizePhone')->andReturnUsing(function ($phone) {
            return ltrim($phone, '+');
        });
        $mock->shouldReceive('send')->andReturn(['status' => 'success']);
    });
});

function createTestOrder(User $driver, string $status = 'driver_assigned'): Order
{
    $client = User::factory()->create(['role' => 'client', 'phone' => '+2250101010101']);
    $supplier = User::factory()->create(['role' => 'fournisseur', 'phone' => '+2250202020202']);

    FournisseurAgree::create([
        'position' => Geo::point(),
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie USSD',
        'statut' => 'agree',
    ]);

    return Order::create([
        'client_id' => $client->id,
        'supplier_id' => $supplier->id,
        'driver_id' => $driver->id,
        'delivery_mode' => 'delivery',
        'status' => $status,
        'subtotal' => 15000,
        'delivery_cost' => 2500,
        'platform_fee' => 450,
        'total_amount' => 17950,
        'pickup_code' => 'LIVREUR-1234',
        'reception_code' => 'RECEPTION-5678',
    ]);
}

test('ussd handle returns main menu for driver on empty text', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);

    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '',
        'sessionId' => 'session_123',
    ]);

    $response->assertStatus(200);
    $response->assertSee('CON ProsArtisan Logistique');
    $response->assertSee('1. Valider Retrait');
    $response->assertSee('2. Valider Livraison');
});

test('ussd handle rejects unregistered phone numbers', function () {
    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250909090909',
        'text' => '',
    ]);

    $response->assertStatus(200);
    $response->assertSee('END Numero non enregistre');
});

test('ussd handle rejects non-driver roles', function () {
    User::factory()->create(['role' => 'client', 'phone' => '+2250404040404']);

    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250404040404',
        'text' => '',
    ]);

    $response->assertStatus(200);
    $response->assertSee('END Acces refuse');
});

test('ussd interactive menu validates order pickup', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    // First choice: 1 (pickup)
    $response1 = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '1',
    ]);
    $response1->assertSee('CON Saisir NoCommande*Code');

    // Second choice: 1*order_id
    $response2 = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '1*'.$order->id,
    ]);
    $response2->assertSee('CON Saisir le code de retrait');

    // Third choice: 1*order_id*LIVREUR-1234
    $response3 = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "1*{$order->id}*LIVREUR-1234",
    ]);
    $response3->assertSee("END Retrait de la commande #{$order->id} valide");

    $this->assertEquals('driver_picked_up', $order->fresh()->status);
});

test('ussd direct dialysis validates order delivery instantly', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_picked_up');

    // Composition directe *555*REC-NoCommande-Code# : le numéro de commande et
    // le code secret (RECEPTION-5678) sont deux valeurs distinctes.
    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "REC-{$order->id}-5678",
    ]);

    $response->assertSee("END Livraison de la commande #{$order->id} validee");
    $this->assertEquals('delivered', $order->fresh()->status);
});

test('le raccourci USSD sans code secret est refuse', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_picked_up');

    // Ancien format : le code était déduit du numéro de commande, qui n'est pas
    // secret. Un livreur pouvait ainsi se payer sans avoir livré.
    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "REC-{$order->id}",
    ]);

    $response->assertSee('END Format incomplet');
    expect($order->fresh()->status)->toBe('driver_picked_up');
});

test('un code derive du numero de commande ne valide plus une livraison', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_picked_up');

    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "REC-{$order->id}-{$order->id}",
    ]);

    $response->assertSee('END Erreur');
    expect($order->fresh()->status)->toBe('driver_picked_up');
});

test('les anciennes constantes universelles ne valident plus rien', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    // « RET-5561 » était accepté sur n'importe quelle commande, quel que soit
    // son code réel (ici LIVREUR-1234).
    $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "RET-{$order->id}-5561",
    ])->assertSee('END Erreur');

    expect($order->fresh()->status)->toBe('driver_assigned');

    // Même chose au stade de la livraison avec « REC-3012 ».
    $order->update(['status' => 'driver_picked_up']);

    $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "REC-{$order->id}-3012",
    ])->assertSee('END Erreur');

    expect($order->fresh()->status)->toBe('driver_picked_up');
});

// ── Authentification de la passerelle ────────────────────────────────────────
// Ces endpoints libèrent des fonds et l'identité du livreur n'est qu'un numéro
// de téléphone posté par l'appelant. Sans authentification de la passerelle,
// n'importe qui pouvait confirmer une livraison qui n'a jamais eu lieu.

test('ussd refuse une requete sans secret de passerelle', function () {
    User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);

    $response = $this->withoutHeader('X-Gateway-Secret')->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '',
    ]);

    $response->assertStatus(401);
    $response->assertDontSee('ProsArtisan Logistique');
});

test('ussd refuse un secret de passerelle errone', function () {
    User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);

    $response = $this->withHeader('X-Gateway-Secret', 'mauvais-secret')
        ->postJson('/api/v1/ussd', [
            'phoneNumber' => '+2250303030303',
            'text' => '',
        ]);

    $response->assertStatus(401);
});

test('sms entrant sans secret ne peut pas valider un retrait', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    $response = $this->withoutHeader('X-Gateway-Secret')->postJson('/api/v1/sms/incoming', [
        'from' => '+2250303030303',
        'message' => "RET-{$order->id}",
    ]);

    $response->assertStatus(401);

    // Le cœur du correctif : la commande n'a pas bougé.
    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('la passerelle reste fermee tant qu aucun secret n est configure', function () {
    config(['services.gateway.secret' => '']);

    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    // Même avec un en-tête, un secret vide ne doit jamais ouvrir l'endpoint :
    // une variable d'environnement oubliée doit provoquer une panne visible.
    $response = $this->postJson('/api/v1/sms/incoming', [
        'from' => '+2250303030303',
        'message' => "RET-{$order->id}",
    ]);

    $response->assertStatus(503);
    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('le secret peut etre porte par un parametre d URL', function () {
    // Repli pour les passerelles qui ne permettent de configurer qu'une URL
    // de rappel, sans en-tête personnalisé (cas courant chez les opérateurs SMS).
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    $response = $this->withoutHeader('X-Gateway-Secret')->postJson(
        '/api/v1/sms/incoming?gateway_secret=secret-passerelle-test',
        ['from' => '+2250303030303', 'message' => "RET {$order->id} 1234"]
    );

    $response->assertOk();
    expect($order->fresh()->status)->toBe('driver_picked_up');
});

test('un parametre d URL errone est refuse', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    $response = $this->withoutHeader('X-Gateway-Secret')->postJson(
        '/api/v1/sms/incoming?gateway_secret=mauvais',
        ['from' => '+2250303030303', 'message' => "RET-{$order->id}"]
    );

    $response->assertStatus(401);
    expect($order->fresh()->status)->toBe('driver_assigned');
});

// ── Signature HMAC de l'opérateur (SMSpro) ───────────────────────────────────
// SMSpro signe chaque appel en HMAC-SHA256 du corps brut. C'est plus fort que
// le secret partagé : la signature couvre le contenu du message.

function signedSmsCall(array $body, string $secret): array
{
    $payload = json_encode($body);

    return [$payload, 'sha256='.hash_hmac('sha256', $payload, $secret)];
}

test('une signature HMAC valide authentifie la passerelle', function () {
    config(['services.gateway.signing_secret' => 'secret-signature-test']);

    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    [$payload, $signature] = signedSmsCall(
        ['from' => '+2250303030303', 'message' => "RET {$order->id} 1234"],
        'secret-signature-test'
    );

    $response = $this->withoutHeader('X-Gateway-Secret')->call(
        'POST',
        '/api/v1/sms/incoming',
        [],
        [],
        [],
        ['HTTP_X-Webhook-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $payload
    );

    $response->assertOk();
    expect($order->fresh()->status)->toBe('driver_picked_up');
});

test('une signature HMAC invalide est refusee', function () {
    config(['services.gateway.signing_secret' => 'secret-signature-test']);

    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    [$payload] = signedSmsCall(
        ['from' => '+2250303030303', 'message' => "RET-{$order->id}"],
        'secret-signature-test'
    );

    $response = $this->withoutHeader('X-Gateway-Secret')->call(
        'POST',
        '/api/v1/sms/incoming',
        [],
        [],
        [],
        ['HTTP_X-Webhook-Signature' => 'sha256=faux', 'CONTENT_TYPE' => 'application/json'],
        $payload
    );

    $response->assertStatus(401);
    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('un corps modifie apres signature est refuse', function () {
    config(['services.gateway.signing_secret' => 'secret-signature-test']);

    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    // Signature calculée sur un contenu, corps remplacé par un autre : c'est
    // précisément ce que le secret partagé seul ne détecterait pas.
    [, $signature] = signedSmsCall(['from' => '+2250303030303', 'message' => 'PING'], 'secret-signature-test');

    $response = $this->withoutHeader('X-Gateway-Secret')->call(
        'POST',
        '/api/v1/sms/incoming',
        [],
        [],
        [],
        ['HTTP_X-Webhook-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
        json_encode(['from' => '+2250303030303', 'message' => "RET-{$order->id}"])
    );

    $response->assertStatus(401);
    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('une signature invalide ne peut pas se rabattre sur le secret partage', function () {
    config(['services.gateway.signing_secret' => 'secret-signature-test']);

    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    // Le bon secret partagé est présent, mais la signature est fausse : le
    // repli constituerait un contournement trivial du contrôle le plus fort.
    $response = $this->call(
        'POST',
        '/api/v1/sms/incoming',
        [],
        [],
        [],
        [
            'HTTP_X-Gateway-Secret' => 'secret-passerelle-test',
            'HTTP_X-Webhook-Signature' => 'sha256=faux',
            'CONTENT_TYPE' => 'application/json',
        ],
        json_encode(['from' => '+2250303030303', 'message' => "RET-{$order->id}"])
    );

    $response->assertStatus(401);
    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('une signature recue sans secret de signature configure est refusee', function () {
    config(['services.gateway.signing_secret' => '']);

    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    [$payload, $signature] = signedSmsCall(
        ['from' => '+2250303030303', 'message' => "RET-{$order->id}"],
        'un-secret-quelconque'
    );

    $response = $this->withoutHeader('X-Gateway-Secret')->call(
        'POST',
        '/api/v1/sms/incoming',
        [],
        [],
        [],
        ['HTTP_X-Webhook-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $payload
    );

    $response->assertStatus(401);
    expect($order->fresh()->status)->toBe('driver_assigned');
});

test('une IP hors liste blanche est refusee', function () {
    config(['services.gateway.ips' => '203.0.113.10,203.0.113.11']);

    User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);

    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '',
    ]);

    $response->assertStatus(403);
});

test('sms incoming callback validates order pickup and replies via SMS', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    // « RET <NoCommande> <code> » : le code secret (LIVREUR-1234) est exigé
    // en plus du numéro de commande.
    $response = $this->postJson('/api/v1/sms/incoming', [
        'from' => '+2250303030303',
        'message' => "RET {$order->id} 1234",
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonFragment([
        'reply' => "ProsArtisan: Retrait de la commande #{$order->id} valide avec succes.",
    ]);

    $this->assertEquals('driver_picked_up', $order->fresh()->status);
});

test('un SMS sans code secret ne valide pas le retrait', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    $response = $this->postJson('/api/v1/sms/incoming', [
        'from' => '+2250303030303',
        'message' => "RET-{$order->id}",
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    // La passerelle est bien authentifiée, mais l'instruction est refusée :
    // on répond au livreur en lui rappelant le format attendu.
    expect($response->json('reply'))->toContain('Format SMS incorrect');
    expect($order->fresh()->status)->toBe('driver_assigned');
});
