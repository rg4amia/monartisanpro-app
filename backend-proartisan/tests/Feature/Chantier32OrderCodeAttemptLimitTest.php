<?php

use App\Models\FournisseurAgree;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo;

/*
 * Chantier 32 — les codes de retrait et de réception ne font que 4 chiffres :
 * sans limite d'essais, un livreur trouvait le code de réception en quelques
 * dizaines de minutes et se faisait régler une course jamais livrée. Par
 * USSD ou SMS, tout livreur pouvait en outre tenter le code de n'importe
 * quelle commande.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif', 'phone' => '+2250101010101']);
    $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif', 'phone' => '+2250202020202']);
    FournisseurAgree::create(['position' => Geo::point(5.3484, -4.0267), 'user_id' => $this->supplier->id, 'nom_boutique' => 'Quincaillerie Centrale', 'statut' => 'agree', 'approuve_at' => now()]);
    $this->driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'phone' => '+2250303030303']);

    // Colis déjà récupéré : le bon code de retrait est accepté sans nouveau
    // versement (rejeu), ce qui isole ici la seule question des essais.
    $this->order = Order::create([
        'client_id' => $this->client->id,
        'supplier_id' => $this->supplier->id,
        'driver_id' => $this->driver->id,
        'status' => 'driver_picked_up',
        'delivery_mode' => 'delivery',
        'subtotal' => 40000,
        'delivery_cost' => 5000,
        'platform_fee' => 1000,
        'total_amount' => 46000,
        'pickup_code' => 'LIVREUR-4821',
        'reception_code' => 'RECEPTION-7390',
        'vehicle_class' => 'moto',
    ]);
});

/** Saisit $times codes de retrait faux et renvoie le dernier message de refus. */
function c32WrongPickups(Order $order, int $times): string
{
    $message = '';
    for ($i = 0; $i < $times; $i++) {
        try {
            app(OrderService::class)->verifyPickup($order->fresh(), '0000');
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
    }

    return $message;
}

test('cinq codes faux suspendent la validation, même pour le bon code', function () {
    expect(c32WrongPickups($this->order, 4))->toBe('Le code de retrait ou de prise en charge est incorrect.');
    expect(c32WrongPickups($this->order, 1))->toContain('suspendue pendant 5 minutes');

    expect(fn () => app(OrderService::class)->verifyPickup($this->order->fresh(), '4821'))
        ->toThrow(Exception::class, 'suspendue');
});

test('un essai pendant la suspension ne teste aucun code et ne la prolonge pas', function () {
    c32WrongPickups($this->order, 5);
    $lockedUntil = $this->order->fresh()->pickup_code_locked_until;

    c32WrongPickups($this->order, 20);

    expect($this->order->fresh()->pickup_code_locked_until->equalTo($lockedUntil))->toBeTrue()
        ->and($this->order->fresh()->pickup_code_attempts)->toBe(5);
});

test('la suspension levée, le bon code est accepté et le compteur repart de zéro', function () {
    c32WrongPickups($this->order, 5);

    $this->travel(16)->minutes();
    app(OrderService::class)->verifyPickup($this->order->fresh(), '4821');

    expect($this->order->fresh()->pickup_code_attempts)->toBe(0)
        ->and($this->order->fresh()->pickup_code_locked_until)->toBeNull();
});

test('chaque nouvelle série de codes faux allonge la suspension', function () {
    expect(c32WrongPickups($this->order, 5))->toContain('5 minutes');

    $this->travel(16)->minutes();
    expect(c32WrongPickups($this->order, 5))->toContain('30 minutes');

    $this->travel(61)->minutes();
    expect(c32WrongPickups($this->order, 5))->toContain('1 heure');

    $this->travel(25)->hours();
    expect(c32WrongPickups($this->order, 5))->toContain('1 heure');
});

test('un bon code avant le seuil remet le compteur à zéro', function () {
    c32WrongPickups($this->order, 4);

    app(OrderService::class)->verifyPickup($this->order->fresh(), '4821');

    expect($this->order->fresh()->pickup_code_attempts)->toBe(0);
    expect(c32WrongPickups($this->order, 4))->toBe('Le code de retrait ou de prise en charge est incorrect.');
});

test('la suspension du code de retrait ne touche pas le code de réception', function () {
    c32WrongPickups($this->order, 5);

    $order = $this->order->fresh();
    expect($order->reception_code_attempts)->toBe(0)
        ->and($order->reception_code_locked_until)->toBeNull();
});

test('les administrateurs sont alertés à chaque suspension', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    c32WrongPickups($this->order, 5);

    $alert = Notification::where('user_id', $admin->id)->where('event_key', 'commande.code_suspendu.admin')->sole();
    expect($alert->body)->toContain('#'.$this->order->id)
        ->and($alert->body)->not->toContain('4821');
});

test('par l\'application, le livreur ne trouve pas le code de réception par essais successifs', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($this->driver, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/verify-delivery", ['code' => sprintf('%04d', $i)])
            ->assertStatus(400);
    }

    $this->actingAs($this->driver, 'sanctum')
        ->postJson("/api/v1/orders/{$this->order->id}/verify-delivery", ['code' => '7390'])
        ->assertStatus(400)
        ->assertJsonPath('success', false);

    expect($this->order->fresh()->status)->toBe('driver_picked_up');
});

test('les compteurs d\'essais ne sont pas transmis avec la commande', function () {
    expect($this->order->fresh()->toArray())
        ->not->toHaveKeys(['pickup_code_attempts', 'pickup_code_locked_until', 'reception_code_attempts', 'reception_code_locked_until']);
});

// ─── USSD / SMS : le livreur de la commande, pas n'importe quel livreur ─────

test('par USSD, un livreur étranger à la commande ne valide rien, même avec le bon code', function () {
    config(['services.gateway.secret' => 'secret-passerelle-test']);
    User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'phone' => '+2250404040404']);

    $this->withHeader('X-Gateway-Secret', 'secret-passerelle-test')
        ->postJson('/api/v1/ussd', ['phoneNumber' => '+2250404040404', 'text' => "REC-{$this->order->id}-7390", 'sessionId' => 's1'])
        ->assertSee('Erreur');

    expect($this->order->fresh()->status)->toBe('driver_picked_up')
        ->and($this->order->fresh()->reception_code_attempts)->toBe(0);
});

test('par SMS, un livreur étranger à la commande ne peut ni valider ni faire suspendre le code', function () {
    config(['services.gateway.secret' => 'secret-passerelle-test']);
    User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'phone' => '+2250404040404']);

    for ($i = 0; $i < 6; $i++) {
        $this->withHeader('X-Gateway-Secret', 'secret-passerelle-test')
            ->postJson('/api/v1/sms/incoming', ['from' => '+2250404040404', 'message' => "REC {$this->order->id} 0000"]);
    }
    $this->withHeader('X-Gateway-Secret', 'secret-passerelle-test')
        ->postJson('/api/v1/sms/incoming', ['from' => '+2250404040404', 'message' => "REC {$this->order->id} 7390"]);

    $order = $this->order->fresh();
    expect($order->status)->toBe('driver_picked_up')
        ->and($order->reception_code_attempts)->toBe(0)
        ->and($order->reception_code_locked_until)->toBeNull();
});
