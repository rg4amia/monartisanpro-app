<?php

use App\Models\Address;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\Geo;
use Tests\Support\Routing;

/*
 * Chantier 43 — l'itinéraire d'une course se demande au serveur.
 *
 * Le téléphone du livreur appelait lui-même un serveur d'itinéraire public, et
 * plaçait la boutique ou le client à un point tiré au hasard quand leurs
 * coordonnées manquaient ; le bouton de guidage y menait.
 */

uses(RefreshDatabase::class);

const C43_BOUTIQUE = ['lat' => 5.3484, 'lng' => -4.0267];
const C43_COMPTE = ['lat' => 5.3200, 'lng' => -4.0200];
const C43_CHANTIER = ['lat' => 5.3550, 'lng' => -3.8850];
const C43_LIVREUR = ['lat' => 5.3000, 'lng' => -4.0100];

beforeEach(function () {
    Routing::fakeOsrm();

    $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $this->client->setPosition(C43_COMPTE['lat'], C43_COMPTE['lng']);

    $this->address = Address::create([
        'user_id' => $this->client->id,
        'recipient_name' => 'Chef de chantier',
        'recipient_phone' => '+2250101010101',
        'address_line' => 'Chantier Bingerville',
        'city' => 'Bingerville',
        'is_default' => true,
    ]);
    $this->address->setPosition(C43_CHANTIER['lat'], C43_CHANTIER['lng']);

    $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
    $this->shop = FournisseurAgree::create([
        'user_id' => $this->supplier->id,
        'nom_boutique' => 'Quincaillerie Centrale',
        'position' => Geo::point(C43_BOUTIQUE['lat'], C43_BOUTIQUE['lng']),
        'statut' => 'agree',
        'approuve_at' => now(),
    ]);
    $this->product = SupplierProduct::create(['supplier_id' => $this->supplier->id, 'sku' => 'CIM-1', 'name' => 'Vis', 'unit_price' => 5000, 'stock_quantity' => 50, 'is_active' => true]);

    $this->driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);
});

function c43Order($test, ?User $driver = null, string $status = 'driver_assigned'): Order
{
    $order = app(OrderService::class)->createOrder(
        $test->client,
        $test->supplier,
        [['supplier_product_id' => $test->product->id, 'quantity' => 1]],
        'delivery',
        'moto',
        1.0,
        null,
        $test->address->fresh(),
    );
    $order->forceFill(['status' => $status, 'driver_id' => $driver?->id])->save();

    return $order->fresh();
}

function c43PickupQuery(): string
{
    return '?leg=pickup&from_lat='.C43_LIVREUR['lat'].'&from_lng='.C43_LIVREUR['lng'];
}

it('trace le trajet du livreur vers la boutique', function () {
    $order = c43Order($this, $this->driver);

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route".c43PickupQuery())
        ->assertOk()
        ->assertJsonPath('data.leg', 'pickup')
        ->assertJsonPath('data.from.lat', C43_LIVREUR['lat'])
        ->assertJsonPath('data.to.lat', C43_BOUTIQUE['lat'])
        ->assertJsonPath('data.to.lng', C43_BOUTIQUE['lng'])
        ->assertJsonPath('data.route.is_fallback', false)
        ->assertJsonStructure(['data' => ['route' => ['distance_km', 'duration_min', 'geometry']]]);
});

it('mène la livraison à l\'adresse figée sur la commande, pas au compte du client', function () {
    $order = c43Order($this, $this->driver, 'driver_picked_up');

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route?leg=delivery")
        ->assertOk()
        ->assertJsonPath('data.from.lat', C43_BOUTIQUE['lat'])
        ->assertJsonPath('data.to.lat', C43_CHANTIER['lat'])
        ->assertJsonPath('data.to.lng', C43_CHANTIER['lng']);
});

it('refuse l\'itinéraire d\'une course attribuée à un autre livreur', function () {
    $order = c43Order($this, $this->driver);
    $other = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

    $this->actingAs($other)
        ->getJson("/api/v1/orders/{$order->id}/route".c43PickupQuery())
        ->assertForbidden();

    // Ni le client ni le fournisseur n'ont à suivre la position du livreur par cette route.
    $this->actingAs($this->client)
        ->getJson("/api/v1/orders/{$order->id}/route?leg=delivery")
        ->assertForbidden();
});

it('ouvre l\'itinéraire d\'une course proposée à tout livreur, tant qu\'elle n\'est pas prise', function () {
    $order = c43Order($this, null, 'searching_driver');

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route".c43PickupQuery())
        ->assertOk();
});

it('exige la position du livreur pour le trajet vers la boutique', function () {
    $order = c43Order($this, $this->driver);

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route?leg=pickup")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_lat', 'from_lng']);

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route?leg=ailleurs")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['leg']);
});

it('ne rend aucun trajet quand la boutique n\'a pas de fiche', function () {
    $order = c43Order($this, $this->driver);
    $this->shop->delete();

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route".c43PickupQuery())
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', "La position de la boutique n'est pas connue.");

    Http::assertNothingSent();
});

it('annonce une estimation quand aucun service d\'itinéraire ne répond', function () {
    $order = c43Order($this, $this->driver, 'driver_picked_up');

    // Le service simulé avant chaque test répondrait encore : on repart d'un client neuf.
    Http::swap(new Factory);
    Http::fake(['*' => Http::response([], 503)]);

    $this->actingAs($this->driver)
        ->getJson("/api/v1/orders/{$order->id}/route?leg=delivery")
        ->assertOk()
        ->assertJsonPath('data.route.is_fallback', true);
});
