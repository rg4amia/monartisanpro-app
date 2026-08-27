<?php

use App\Models\User;
use App\Models\Order;
use App\Services\SmsService;
use App\Services\GoogleMapsService;

beforeEach(function () {
    $this->mock(GoogleMapsService::class, function ($mock) {
        $mock->shouldReceive('getDirections')->andReturn([
            'distance' => 5000,
            'duration' => 600,
            'source'   => 'mocked',
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

    \App\Models\FournisseurAgree::create([
        'user_id'      => $supplier->id,
        'nom_boutique' => 'Quincaillerie USSD',
        'statut'       => 'agree',
    ]);

    return Order::create([
        'client_id'                 => $client->id,
        'supplier_id'               => $supplier->id,
        'driver_id'                 => $driver->id,
        'delivery_mode'             => 'delivery',
        'status'                    => $status,
        'subtotal'                  => 15000,
        'delivery_cost'             => 2500,
        'platform_fee'              => 450,
        'total_amount'              => 17950,
        'pickup_code'               => 'LIVREUR-1234',
        'reception_code'            => 'RECEPTION-5678',
    ]);
}

test('ussd handle returns main menu for driver on empty text', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);

    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '',
        'sessionId' => 'session_123'
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
    $response1->assertSee('CON Saisir ID_Commande*Code');

    // Second choice: 1*order_id
    $response2 = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => '1*' . $order->id,
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

    // Direct dialing *555*REC-orderId#
    $response = $this->postJson('/api/v1/ussd', [
        'phoneNumber' => '+2250303030303',
        'text' => "REC-{$order->id}",
    ]);

    $response->assertSee("END Livraison de la commande #{$order->id} validee");
    $this->assertEquals('delivered', $order->fresh()->status);
});

test('sms incoming callback validates order pickup and replies via SMS', function () {
    $driver = User::factory()->create(['role' => 'livreur', 'phone' => '+2250303030303']);
    $order = createTestOrder($driver, 'driver_assigned');

    $response = $this->postJson('/api/v1/sms/incoming', [
        'from' => '+2250303030303',
        'message' => "RET-{$order->id}"
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonFragment([
        'reply' => "ProsArtisan: Retrait de la commande #{$order->id} valide avec succes."
    ]);

    $this->assertEquals('driver_picked_up', $order->fresh()->status);
});
