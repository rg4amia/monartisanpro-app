<?php

use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\GoogleMapsService;
use App\Services\OsrmRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Geo;

/*
 * La majoration de course (surge) est une décision financière : elle se juge
 * sur la valeur établie par le serveur (heure de pointe, réglage plateforme),
 * jamais sur celle postée par le client (Règle d'or 36). L'application
 * proposait un curseur de majoration au client, que le serveur enregistrait
 * tel quel sur la commande puis réutilisait pour calculer la course finale.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(GoogleMapsService::class, fn ($mock) => $mock->shouldReceive('getDirections')->andReturn(['distance' => 5000, 'duration' => 600, 'source' => 'mocked']));
    $this->mock(OsrmRoutingService::class, fn ($mock) => $mock->shouldReceive('getRoute')->andReturn([
        'distance_meters' => 4500, 'duration_seconds' => 500, 'distance_km' => 4.5, 'duration_minutes' => 8, 'source' => 'mocked_osrm',
    ]));

    $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $this->address = Address::create([
        'user_id' => $this->client->id,
        'recipient_name' => 'Client Test',
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Cocody Angré',
        'city' => 'Abidjan',
        'is_default' => true,
    ]);
    $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $this->supplier->id, 'nom_boutique' => 'Quincaillerie Nord', 'statut' => 'agree', 'approuve_at' => now()]);
    $this->product = SupplierProduct::create(['supplier_id' => $this->supplier->id, 'sku' => 'CIM-1', 'name' => 'Ciment', 'unit_price' => 5000, 'stock_quantity' => 50, 'is_active' => true]);
});

afterEach(fn () => Carbon::setTestNow());

test('la majoration postée par le client est ignorée à la commande simple', function () {
    // Midi à Abidjan : aucune heure de pointe, majoration serveur = 1,0.
    Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', 'Africa/Abidjan'));

    $this->actingAs($this->client)->postJson('/api/v1/orders', [
        'supplier_id' => $this->supplier->id,
        'delivery_mode' => 'delivery',
        'address_id' => $this->address->id,
        'vehicle_class' => 'moto',
        'surge_multiplier' => 3.0,
        'items' => [['supplier_product_id' => $this->product->id, 'quantity' => 1]],
    ])->assertCreated();

    expect((float) Order::sole()->surge_multiplier)->toBe(1.0);
});

test('la majoration d\'heure de pointe s\'applique même si le client envoie 1,0', function () {
    // 18 h à Abidjan : heure de pointe du soir, majoration serveur = 1,4.
    Carbon::setTestNow(Carbon::parse('2026-09-29 18:00:00', 'Africa/Abidjan'));

    $this->actingAs($this->client)->postJson('/api/v1/orders/multi-store', [
        'address_id' => $this->address->id,
        'packages' => [[
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'delivery',
            'vehicle_class' => 'moto',
            'surge_multiplier' => 1.0,
            'items' => [['supplier_product_id' => $this->product->id, 'quantity' => 1]],
        ]],
    ])->assertCreated();

    expect((float) Order::sole()->surge_multiplier)->toBe(1.4);
});
