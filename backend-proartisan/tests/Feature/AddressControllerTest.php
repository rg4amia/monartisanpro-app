<?php

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAddress(User $user, array $overrides = []): Address
{
    return Address::create(array_merge([
        'user_id' => $user->id,
        'label' => 'Domicile',
        'recipient_name' => $user->name ?? 'Destinataire',
        'recipient_phone' => '+2250700000000',
        'address_line' => 'Cocody Angré 8e Tranche',
        'city' => 'Abidjan',
        'is_default' => false,
    ], $overrides));
}

test('client can create their first address and it becomes the default', function () {
    $client = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->postJson('/api/v1/addresses', [
        'label' => 'Domicile',
        'recipient_name' => 'Bamba Inza',
        'recipient_phone' => '+2250707262811',
        'address_line' => 'Cocody Angré 8e Tranche',
        'city' => 'Abidjan',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.isDefault', true);
    $response->assertJsonPath('data.recipientName', 'Bamba Inza');

    $this->assertDatabaseHas('addresses', [
        'user_id' => $client->id,
        'recipient_name' => 'Bamba Inza',
        'is_default' => true,
    ]);
});

test('a second address is not default unless requested', function () {
    $client = User::factory()->create(['role' => 'client']);
    createAddress($client, ['is_default' => true]);

    $response = $this->actingAs($client)->postJson('/api/v1/addresses', [
        'recipient_name' => 'Bureau',
        'recipient_phone' => '+2250707262811',
        'address_line' => 'Plateau, Rue du Commerce',
        'city' => 'Abidjan',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.isDefault', false);
});

test('setting a new default unsets the previous one', function () {
    $client = User::factory()->create(['role' => 'client']);
    $first = createAddress($client, ['is_default' => true]);
    $second = createAddress($client);

    $response = $this->actingAs($client)->postJson("/api/v1/addresses/{$second->id}/default");

    $response->assertOk();
    $this->assertFalse($first->fresh()->is_default);
    $this->assertTrue($second->fresh()->is_default);
});

test('deleting the default address promotes the most recent remaining one', function () {
    $client = User::factory()->create(['role' => 'client']);
    $old = createAddress($client, ['created_at' => now()->subDay()]);
    $default = createAddress($client, ['is_default' => true]);

    $this->actingAs($client)->deleteJson("/api/v1/addresses/{$default->id}")->assertOk();

    $this->assertTrue($old->fresh()->is_default);
});

test('a client cannot see, edit, delete or default another clients address', function () {
    $owner = User::factory()->create(['role' => 'client']);
    $attacker = User::factory()->create(['role' => 'client']);
    $address = createAddress($owner, ['is_default' => true]);

    $this->actingAs($attacker)
        ->putJson("/api/v1/addresses/{$address->id}", ['city' => 'Bouaké'])
        ->assertStatus(403);

    $this->actingAs($attacker)
        ->deleteJson("/api/v1/addresses/{$address->id}")
        ->assertStatus(403);

    $this->actingAs($attacker)
        ->postJson("/api/v1/addresses/{$address->id}/default")
        ->assertStatus(403);

    $addresses = $this->actingAs($attacker)->getJson('/api/v1/addresses')->json('data');
    expect($addresses)->toBeEmpty();
});

test('deleting an address does not corrupt the frozen snapshot on a past order', function () {
    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $supplier = User::factory()->create(['role' => 'fournisseur']);
    \App\Models\FournisseurAgree::create([
        'user_id' => $supplier->id,
        'nom_boutique' => 'Quincaillerie Test',
        'statut' => 'agree',
    ]);
    $product = \App\Models\SupplierProduct::create([
        'supplier_id' => $supplier->id,
        'sku' => 'ADDR-TEST',
        'name' => 'Ciment',
        'unit_price' => 5000,
        'stock_quantity' => 10,
    ]);

    $address = createAddress($client, [
        'is_default' => true,
        'recipient_name' => 'Bamba Inza',
        'recipient_phone' => '+2250707262811',
        'address_line' => 'Cocody Angré 8e Tranche',
        'city' => 'Abidjan',
    ]);

    $order = app(\App\Services\OrderService::class)->createOrder(
        client: $client,
        supplier: $supplier,
        items: [['supplier_product_id' => $product->id, 'quantity' => 1]],
        deliveryMode: 'delivery',
        address: $address,
    );

    expect($order->address_id)->toBe($address->id);
    expect($order->recipient_name)->toBe('Bamba Inza');

    $this->actingAs($client)->deleteJson("/api/v1/addresses/{$address->id}")->assertOk();

    $order->refresh();

    // La clé étrangère se détache (nullOnDelete), mais le nom, le téléphone,
    // l'adresse et la ville figés à la création de la commande survivent
    // intacts à la suppression de l'adresse source dans le carnet.
    expect($order->address_id)->toBeNull();
    expect($order->recipient_name)->toBe('Bamba Inza');
    expect($order->recipient_phone)->toBe('+2250707262811');
    expect($order->delivery_address_line)->toBe('Cocody Angré 8e Tranche');
    expect($order->delivery_city)->toBe('Abidjan');
});

test('an invalid recipient phone format is rejected', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->postJson('/api/v1/addresses', [
        'recipient_name' => 'Bamba Inza',
        'recipient_phone' => '0707262811',
        'address_line' => 'Cocody Angré',
        'city' => 'Abidjan',
    ])->assertStatus(422);
});
