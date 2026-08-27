<?php

use App\Models\FournisseurAgree;
use App\Models\JCode;
use App\Models\JCodeItem;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\GeoService;
use App\Services\GoogleMapsService;
use App\Services\JCodeService;
use App\Services\SupplierCatalogService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Mock le GPS pour valider automatiquement (< 100m)
    $this->mock(GeoService::class, function ($mock) {
        $mock->shouldReceive('validateJCodeGps')->andReturn([
            'valid'    => true,
            'distance' => 15,
            'max'      => 100,
        ]);
    });

    // Mock le SupplierCatalogService pour ne pas bloquer
    $this->mock(SupplierCatalogService::class, function ($mock) {
        $mock->shouldReceive('ensureApprovedSupplier')->andReturnNull();
        $mock->shouldReceive('decrementStockForServedItem')->andReturnNull();
    });

    $this->mock(GoogleMapsService::class, function ($mock) {
        $mock->shouldReceive('getDirections')->andReturn([
            'distance' => 5000,
            'duration' => 600,
            'source'   => 'mocked',
        ]);
    });
});

/**
 * Helper : crée un J-Code avec N items pour les tests de consommation partielle.
 */
function createJCodeWithItems(int $itemCount = 4, int $unitPrice = 5000): array
{
    $artisan = User::factory()->create([
        'role' => 'artisan',
        'kyc_status' => 'actif',
        'wallet_materiaux' => 1000000,
        'phone' => '+225040' . rand(1000000, 9999999)
    ]);
    $fournisseur1 = User::factory()->create([
        'role' => 'fournisseur',
        'kyc_status' => 'actif',
        'phone' => '+225050' . rand(1000000, 9999999)
    ]);
    $fournisseur2 = User::factory()->create([
        'role' => 'fournisseur',
        'kyc_status' => 'actif',
        'phone' => '+225060' . rand(1000000, 9999999)
    ]);
    $client = User::factory()->create([
        'role' => 'client',
        'kyc_status' => 'actif',
        'phone' => '+225070' . rand(1000000, 9999999)
    ]);

    FournisseurAgree::create([
        'user_id'      => $fournisseur1->id,
        'nom_boutique' => 'Quincaillerie A',
        'statut'       => 'agree',
    ]);
    FournisseurAgree::create([
        'user_id'      => $fournisseur2->id,
        'nom_boutique' => 'Quincaillerie B',
        'statut'       => 'agree',
    ]);

    $mission = Mission::create([
        'artisan_id' => $artisan->id,
        'client_id'  => $client->id,
        'description' => 'Test Mission',
        'status'     => 'funded_locked',
        'montant_total' => $unitPrice * $itemCount,
        'montant_materiaux' => $unitPrice * $itemCount,
        'montant_mo' => 0,
        'ratio_materiaux' => 1.0,
    ]);

    $montantTotal = $unitPrice * $itemCount;

    $jcode = JCode::create([
        'mission_id'      => $mission->id,
        'artisan_id'      => $artisan->id,
        'fournisseur_id'  => $fournisseur1->id,
        'code'            => 'PA-' . strtoupper(substr(md5(rand()), 0, 4)),
        'ussd_code'       => '*555*TEST#',
        'montant'         => $montantTotal,
        'montant_consomme'=> 0,
        'statut'          => 'actif',
        'expires_at'      => now()->addHours(48),
    ]);

    $items = [];
    for ($i = 0; $i < $itemCount; $i++) {
        $items[] = JCodeItem::create([
            'jcode_id'    => $jcode->id,
            'source'      => 'custom',
            'item_name'   => "Ciment sac " . ($i + 1),
            'quantity'    => 1,
            'quantity_served' => 0,
            'unit_price'  => $unitPrice,
            'subtotal'    => $unitPrice,
            'status'      => 'requested',
        ]);
    }

    return compact('jcode', 'items', 'artisan', 'fournisseur1', 'fournisseur2', 'client', 'mission');
}

// ─── Test 1 : Scan partiel → statut partiellement_utilise ─────────────────────

test('partial scan sets jcode to partiellement_utilise with correct montant_consomme', function () {
    $data = createJCodeWithItems(4, 5000); // 4 items × 5000 = 20000 FCFA

    $service = app(JCodeService::class);

    // Fournisseur 1 scanne seulement 2 items sur 4
    $result = $service->scan(
        $data['jcode'],
        $data['fournisseur1'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][0]->id, 'quantity_served' => 1],
            ['jcode_item_id' => $data['items'][1]->id, 'quantity_served' => 1],
        ]
    );

    $data['jcode']->refresh();

    expect($data['jcode']->statut)->toBe('partiellement_utilise');
    expect($data['jcode']->montant_consomme)->toBe(10000);
    expect($data['jcode']->montant_restant)->toBe(10000);
    expect($result['fully_consumed'])->toBeFalse();
    expect($result['montant_servi'])->toBe(10000);
});

// ─── Test 2 : Second scan complète le J-Code ─────────────────────────────────

test('second scan by different supplier completes jcode to utilise', function () {
    $data = createJCodeWithItems(4, 5000);

    $service = app(JCodeService::class);

    // Premier scan : fournisseur 1 sert 2 items
    $service->scan(
        $data['jcode'],
        $data['fournisseur1'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][0]->id, 'quantity_served' => 1],
            ['jcode_item_id' => $data['items'][1]->id, 'quantity_served' => 1],
        ]
    );

    $data['jcode']->refresh();
    expect($data['jcode']->statut)->toBe('partiellement_utilise');

    // Second scan : fournisseur 2 sert les 2 items restants
    $result = $service->scan(
        $data['jcode'],
        $data['fournisseur2'],
        5.320000, -4.030000,
        [
            ['jcode_item_id' => $data['items'][2]->id, 'quantity_served' => 1],
            ['jcode_item_id' => $data['items'][3]->id, 'quantity_served' => 1],
        ]
    );

    $data['jcode']->refresh();

    expect($data['jcode']->statut)->toBe('utilise');
    expect($data['jcode']->montant_consomme)->toBe(20000);
    expect($data['jcode']->montant_restant)->toBe(0);
    expect($result['fully_consumed'])->toBeTrue();
});

// ─── Test 3 : Scan total (tous les items d'un coup) → utilise directement ───

test('full scan sets jcode directly to utilise', function () {
    $data = createJCodeWithItems(2, 5000);

    $service = app(JCodeService::class);

    $result = $service->scan(
        $data['jcode'],
        $data['fournisseur1'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][0]->id, 'quantity_served' => 1],
            ['jcode_item_id' => $data['items'][1]->id, 'quantity_served' => 1],
        ]
    );

    $data['jcode']->refresh();

    expect($data['jcode']->statut)->toBe('utilise');
    expect($data['jcode']->montant_consomme)->toBe(10000);
    expect($data['jcode']->montant_restant)->toBe(0);
    expect($result['fully_consumed'])->toBeTrue();
});

// ─── Test 4 : J-Code partiellement consommé reste actif ──────────────────────

test('isActif returns true for partiellement_utilise jcode', function () {
    $data = createJCodeWithItems(4, 5000);

    $data['jcode']->update([
        'statut'           => 'partiellement_utilise',
        'montant_consomme' => 10000,
    ]);

    $data['jcode']->refresh();

    expect($data['jcode']->isActif())->toBeTrue();
    expect($data['jcode']->isPartiallyConsumed())->toBeTrue();
    expect($data['jcode']->isFullyConsumed())->toBeFalse();
});

// ─── Test 5 : PaySupplierJob dispatché avec le bon montant partiel ───────────

test('pay supplier job is dispatched with partial amount', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $data = createJCodeWithItems(4, 5000);

    $service = app(JCodeService::class);

    $service->scan(
        $data['jcode'],
        $data['fournisseur1'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][0]->id, 'quantity_served' => 1],
        ]
    );

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PaySupplierJob::class, function ($job) use ($data) {
        return $job->jcodeId === $data['jcode']->id
            && $job->fournisseurId === $data['fournisseur1']->id
            && $job->montantServi === 5000;
    });
});

// ─── Test 6 : GPS obligatoire sur chaque scan (y compris le second) ──────────

test('gps verification is required on every scan including second supplier', function () {
    // Overrider le mock GPS pour rejeter
    $this->mock(GeoService::class, function ($mock) {
        $mock->shouldReceive('validateJCodeGps')->andReturn([
            'valid'    => false,
            'distance' => 250,
            'max'      => 100,
        ]);
    });

    $data = createJCodeWithItems(2, 5000);

    // Forcer le statut partiellement_utilise
    $data['jcode']->update([
        'statut'           => 'partiellement_utilise',
        'montant_consomme' => 5000,
    ]);

    $service = app(JCodeService::class);

    expect(fn () => $service->scan(
        $data['jcode'],
        $data['fournisseur2'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][1]->id, 'quantity_served' => 1],
        ]
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

// ─── Test 7 : Quantité servie > quantité demandée → rejet ───────────────────

test('serving more than requested quantity is rejected', function () {
    $data = createJCodeWithItems(2, 5000);

    $service = app(JCodeService::class);

    expect(fn () => $service->scan(
        $data['jcode'],
        $data['fournisseur1'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][0]->id, 'quantity_served' => 99],
        ]
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

// ─── Test 8 : J-Code expiré partiellement consommé ne peut plus être scanné ──

test('expired partially consumed jcode cannot be scanned', function () {
    $data = createJCodeWithItems(4, 5000);

    $data['jcode']->update([
        'statut'           => 'partiellement_utilise',
        'montant_consomme' => 10000,
        'expires_at'       => now()->subHour(), // Expiré
    ]);

    $data['jcode']->refresh();

    expect($data['jcode']->isActif())->toBeFalse();

    $service = app(JCodeService::class);

    expect(fn () => $service->scan(
        $data['jcode'],
        $data['fournisseur2'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][2]->id, 'quantity_served' => 1],
        ]
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

// ─── Test 9 : Items individuels traçés par fournisseur ───────────────────────

test('each item tracks which supplier served it', function () {
    $data = createJCodeWithItems(4, 5000);

    $service = app(JCodeService::class);

    // Fournisseur 1 sert items 0 et 1
    $service->scan(
        $data['jcode'],
        $data['fournisseur1'],
        5.316667, -4.033333,
        [
            ['jcode_item_id' => $data['items'][0]->id, 'quantity_served' => 1],
            ['jcode_item_id' => $data['items'][1]->id, 'quantity_served' => 1],
        ]
    );

    $data['items'][0]->refresh();
    $data['items'][1]->refresh();
    $data['items'][2]->refresh();

    expect($data['items'][0]->served_by_supplier_id)->toBe($data['fournisseur1']->id);
    expect($data['items'][0]->status)->toBe('served');
    expect($data['items'][1]->served_by_supplier_id)->toBe($data['fournisseur1']->id);
    expect($data['items'][2]->served_by_supplier_id)->toBeNull();
    expect($data['items'][2]->status)->toBe('requested');
});
