<?php

namespace Tests\Feature;

use App\Models\DriverCashout;
use App\Models\Jalon;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Order;
use App\Models\SupplierCashout;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Geo;
use Tests\TestCase;

/**
 * Journée type de l'écosystème ProsArtisan, entièrement via l'API, du point
 * de vue de chaque acteur : demandes de devis refusées puis acceptées, devis
 * refusé puis accepté, financement du séquestre, paiement des étapes par OTP,
 * litige arbitré, commande de matériaux livrée, course payée, puis retraits
 * du livreur et de la quincaillerie. Les audits de trésorerie concluent.
 *
 * Les tests de chaque module existent déjà isolément ; celui-ci vérifie que
 * les modules s'enchaînent sans que l'argent se perde entre eux.
 */
class EcosystemJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private User $artisanA;

    private User $artisanB;

    private User $supplier;

    private User $driver;

    private SupplierProduct $ciment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif', 'phone' => '+2250000000000']);
        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif', 'phone' => '+2250701000001']);
        $this->client->setPosition(5.3599, -4.0083);
        $this->artisanA = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'phone' => '+2250701000002']);
        $this->artisanB = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'phone' => '+2250701000003', 'payment_phone' => '+2250701000003']);
        $this->driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'phone' => '+2250701000005', 'payment_phone' => '+2250701000005']);
        $this->driver->setPosition(5.3450, -3.9900);

        $this->supplier = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif', 'phone' => '+2250701000004', 'payment_phone' => '+2250701000004']);
        $this->supplier->setPosition(5.3400, -3.9800);
        $this->supplier->fournisseurAgree()->create([
            'nom_boutique' => 'Quincaillerie de Treichville',
            'position' => Geo::point(),
            'statut' => 'agree',
            'approuve_at' => now(),
        ])->setPosition(5.34, -3.98);

        $this->ciment = SupplierProduct::create([
            'supplier_id' => $this->supplier->id,
            'sku' => 'CIM-ECO',
            'name' => 'Ciment CPA 45 (sac de 50 kg)',
            'unit_price' => 5000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
    }

    private function step(string $label): void
    {
        fwrite(STDERR, "\n  ▸ {$label}");
    }

    public function test_une_journee_complete_de_l_ecosysteme(): void
    {
        fwrite(STDERR, "\n\n══ ProsArtisan — simulation de l'écosystème ══");

        $mission = $this->missionAvecRefusPuisAcceptation();
        $this->paiementDesEtapes($mission);
        $this->litigeArbitre();
        $this->commandeLivree();
        $this->decaissements();
        $this->auditsDeTresorerie();

        fwrite(STDERR, "\n\n══ Fin de la simulation : tous les parcours sont cohérents ══\n");
    }

    // ─── 1. Demande de devis refusée, réaffectée, devis refusé puis accepté ───

    private function missionAvecRefusPuisAcceptation(): Mission
    {
        $this->step('Client : demande de devis à l\'artisan A (maçonnerie, Cocody)');
        $missionId = $this->actingAs($this->client)->postJson('/api/v1/missions', [
            'artisan_id' => $this->artisanA->id,
            'category' => 'Maconnerie',
            'description' => 'Refaire la dalle de la cuisine et la chape du salon, fourniture et pose comprises.',
            'lat' => 5.351,
            'lng' => -4.021,
            'location_address' => 'Cocody Angré 8e Tranche',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending_artisan_acceptance')
            ->json('data.id');
        $mission = Mission::findOrFail($missionId);

        $this->step('Artisan B (non désigné) tente d\'accepter la demande → refusé');
        $this->actingAs($this->artisanB)
            ->postJson("/api/v1/missions/{$mission->id}/accept-request")
            ->assertForbidden();

        $this->step('Artisan A refuse la demande de devis → mission remise en brouillon');
        $this->actingAs($this->artisanA)
            ->postJson("/api/v1/missions/{$mission->id}/reject-request")
            ->assertOk();
        $this->assertSame('draft', (string) $mission->fresh()->status);

        $this->step('Client : réaffecte la demande à l\'artisan B');
        $this->actingAs($this->client)
            ->postJson("/api/v1/missions/{$mission->id}/assign-artisan", ['artisan_id' => $this->artisanB->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_artisan_acceptance');

        $this->step("Artisan B : devis refusé tant qu'il n'a pas accepté la demande (Règle d'or 43)");
        $this->actingAs($this->artisanB)
            ->postJson("/api/v1/missions/{$mission->id}/devis", $this->devisPayload(40000))
            ->assertUnprocessable();

        $this->step('Artisan B accepte la demande, puis soumet un premier devis (MO 40 000)');
        $this->actingAs($this->artisanB)
            ->postJson("/api/v1/missions/{$mission->id}/accept-request")
            ->assertOk();
        $premierDevisId = $this->actingAs($this->artisanB)
            ->postJson("/api/v1/missions/{$mission->id}/devis", $this->devisPayload(40000))
            ->assertCreated()
            ->json('data.id');

        $this->step('Artisan B tente d\'accepter son propre devis → refusé (réservé au client)');
        $this->actingAs($this->artisanB)
            ->postJson("/api/v1/devis/{$premierDevisId}/accept", [])
            ->assertForbidden();

        $this->step('Client refuse le premier devis (trop cher)');
        $this->actingAs($this->client)
            ->postJson("/api/v1/devis/{$premierDevisId}/refuse")
            ->assertOk();
        $this->assertDatabaseHas('devis', ['id' => $premierDevisId, 'statut' => 'refuse']);

        $this->step('Client : redemande un devis à l\'artisan B, qui accepte et soumet un devis révisé (MO 35 000)');
        $this->actingAs($this->client)
            ->postJson("/api/v1/missions/{$mission->id}/assign-artisan", ['artisan_id' => $this->artisanB->id])
            ->assertOk();
        $this->actingAs($this->artisanB)
            ->postJson("/api/v1/missions/{$mission->id}/accept-request")
            ->assertOk();
        $devisId = $this->actingAs($this->artisanB)
            ->postJson("/api/v1/missions/{$mission->id}/devis", $this->devisPayload(35000))
            ->assertCreated()
            ->json('data.id');

        $this->step('Client : paiement Wave du devis (simulateur) puis acceptation');
        $transactionId = $this->actingAs($this->client)->postJson('/api/v1/payments/initiate', [
            'mission_id' => $mission->id,
            'devis_id' => $devisId,
            'montant' => 100,
            'provider' => 'wave',
            'phone' => $this->client->phone,
        ])->assertOk()->json('data.transaction_id');

        $this->actingAs($this->client)
            ->getJson("/api/v1/payments/{$transactionId}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirme');

        $this->actingAs($this->client)
            ->postJson("/api/v1/devis/{$devisId}/accept", ['transaction_id' => $transactionId])
            ->assertOk()
            ->assertJsonPath('data.missionStatus', 'funded_locked');

        $paye = (int) Transaction::findOrFail($transactionId)->montant;
        $this->assertGreaterThan(100, $paye, 'Le montant encaissé est fixé par le serveur, pas par le client.');
        $this->step("   séquestre financé : {$paye} FCFA encaissés, montant imposé par le serveur (100 FCFA postés ignorés)");

        $mission->refresh();
        $this->assertSame('funded_locked', (string) $mission->status);
        $this->assertSame($this->artisanB->id, $mission->artisan_id);
        $this->assertSame(35000, $this->artisanB->fresh()->wallet_mo);

        return $mission;
    }

    private function devisPayload(int $mainOeuvre): array
    {
        $premier = intdiv($mainOeuvre, 2);

        return [
            'lignes' => [
                ['type' => 'mat', 'description' => 'Ciment CPA 45', 'montant' => 50000, 'source' => 'catalog',
                    'supplier_product_id' => $this->ciment->id, 'quantity' => 10, 'unit_price' => 5000],
                ['type' => 'mo', 'description' => 'Coulage et finitions', 'montant' => $mainOeuvre],
            ],
            'jalons' => [
                ['ordre' => 1, 'description' => 'Coulage de la dalle', 'montant' => $premier, 'date_cible' => now()->addDay()->toDateString()],
                ['ordre' => 2, 'description' => 'Chape et finitions', 'montant' => $mainOeuvre - $premier, 'date_cible' => now()->addDays(3)->toDateString()],
            ],
        ];
    }

    // ─── 2. Achat des matériaux par J-Code et paiement des étapes par OTP ───

    private function paiementDesEtapes(Mission $mission): void
    {
        $this->step('Artisan B : J-Code matériaux, scanné à la quincaillerie (GPS < 100 m)');
        $code = $this->actingAs($this->artisanB)->postJson('/api/v1/jcodes', [
            'mission_id' => $mission->id,
            'fournisseur_id' => $this->supplier->id,
            'montant' => 50000,
            'items' => [['supplier_product_id' => $this->ciment->id, 'quantity' => 10]],
        ])->assertCreated()->json('data.code');

        $this->step('   scan à 2 km de la boutique → bloqué (Règle d\'or 3)');
        $this->actingAs($this->supplier)
            ->postJson("/api/v1/jcodes/{$code}/scan", ['lat' => 5.36, 'lng' => -3.98])
            ->assertStatus(422);

        $this->actingAs($this->supplier)
            ->postJson("/api/v1/jcodes/{$code}/scan", ['lat' => 5.34, 'lng' => -3.98])
            ->assertOk()
            ->assertJsonPath('data.valid', true);
        $this->assertSame(90, (int) $this->ciment->fresh()->stock_quantity);

        foreach ($mission->jalons()->orderBy('ordre')->get() as $index => $jalon) {
            $this->step("Étape {$jalon->ordre} : preuves photo, OTP envoyé au client, validation");
            $this->actingAs($this->artisanB)->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                'photos' => [[
                    'url' => "https://example.test/jalon-{$jalon->id}.jpg",
                    'lat' => 5.351 + ($index / 1000),
                    'lng' => -4.021,
                    'taken_at' => now()->toIso8601String(),
                ]],
            ])->assertOk();

            $this->actingAs($this->artisanB)->postJson("/api/v1/jalons/{$jalon->id}/request-otp")->assertOk();
            $jalon->refresh();

            if ($index === 0) {
                $this->step('   OTP erroné → aucun versement (Règle d\'or 4)');
                $this->actingAs($this->client)
                    ->postJson("/api/v1/jalons/{$jalon->id}/validate-otp", ['otp' => '000000'])
                    ->assertStatus(422);
                $this->assertNotSame('paye', $jalon->fresh()->statut);
            }

            // Délai d'inspection réaliste par le client (> 120 s, garde anti-collusion).
            Jalon::withoutTimestamps(fn () => $jalon->forceFill(['updated_at' => now()->subMinutes(15)])->save());

            $this->actingAs($this->client)
                ->postJson("/api/v1/jalons/{$jalon->id}/validate-otp", ['otp' => $jalon->otp_code])
                ->assertOk();
            $this->assertSame('paye', $jalon->fresh()->statut);
            $this->step("   {$jalon->montant} FCFA versés à l'artisan par Mobile Money");
        }

        $this->assertSame('completed', (string) $mission->fresh()->status);
        $this->assertSame(0, $this->artisanB->fresh()->wallet_mo, 'Toute la main d\'œuvre a été libérée.');

        $this->step('Client : évaluation de l\'artisan (4 piliers)');
        $this->actingAs($this->client)->postJson('/api/v1/evaluations', [
            'mission_id' => $mission->id,
            'evalue_id' => $this->artisanB->id,
            'note' => 5,
            'commentaire' => 'Travail propre et dans les délais.',
            'fiabilite' => 5, 'integrite' => 5, 'qualite' => 5, 'reactivite' => 4,
        ])->assertCreated();
        $this->assertGreaterThan(0, $this->artisanB->fresh()->score_prosartisan);
        $this->step("   score ProsArtisan de l'artisan B : 0 → {$this->artisanB->fresh()->score_prosartisan}/1000");
    }

    // ─── 3. Seconde mission : litige, preuves des deux parties, arbitrage ───

    private function litigeArbitre(): void
    {
        $this->step('Seconde mission (plomberie) avec l\'artisan A, financée');
        $missionId = $this->actingAs($this->client)->postJson('/api/v1/missions', [
            'artisan_id' => $this->artisanA->id,
            'category' => 'Plomberie',
            'description' => 'Remplacer la colonne d\'eau de la salle de bain et reprendre les raccords fuyants.',
            'lat' => 5.351,
            'lng' => -4.021,
            'location_address' => 'Cocody Angré 8e Tranche',
        ])->assertCreated()->json('data.id');
        $mission = Mission::findOrFail($missionId);

        $this->actingAs($this->artisanA)->postJson("/api/v1/missions/{$mission->id}/accept-request")->assertOk();
        $devisId = $this->actingAs($this->artisanA)->postJson("/api/v1/missions/{$mission->id}/devis", [
            'lignes' => [['type' => 'mo', 'description' => 'Remplacement colonne', 'montant' => 60000]],
            'jalons' => [['ordre' => 1, 'description' => 'Remplacement complet', 'montant' => 60000, 'date_cible' => now()->addDays(2)->toDateString()]],
        ])->assertCreated()->json('data.id');

        $transactionId = $this->actingAs($this->client)->postJson('/api/v1/payments/initiate', [
            'mission_id' => $mission->id, 'devis_id' => $devisId, 'montant' => 100, 'provider' => 'orange_money', 'phone' => $this->client->phone,
        ])->assertOk()->json('data.transaction_id');
        $this->actingAs($this->client)->getJson("/api/v1/payments/{$transactionId}/status")->assertOk();
        $this->actingAs($this->client)
            ->postJson("/api/v1/devis/{$devisId}/accept", ['transaction_id' => $transactionId])
            ->assertOk();
        $this->assertSame(60000, $this->artisanA->fresh()->wallet_mo);

        $this->step('Client : déclare un litige (fuite persistante) → fonds gelés');
        $litigeId = $this->actingAs($this->client)->postJson('/api/v1/litiges', [
            'mission_id' => $mission->id,
            'motif' => 'Travail non conforme',
            'description' => 'La colonne fuit toujours au niveau des raccords, le plafond du voisin est mouillé.',
        ])->assertCreated()
            ->assertJsonPath('data.workflowStep', 'preuves')
            ->json('data.id');
        $this->assertTrue((bool) $mission->fresh()->funds_frozen);
        $this->assertSame('disputed', (string) $mission->fresh()->status);

        $this->step('   un tiers (artisan B) ne peut pas consulter la fiche litige');
        $this->actingAs($this->artisanB)->getJson("/api/v1/litiges/{$litigeId}")->assertForbidden();

        $this->step('Preuves géolocalisées : 2 photos client, 1 photo artisan → phase d\'arbitrage');
        $this->actingAs($this->client)->post("/api/v1/litiges/{$litigeId}/preuves", ['photos' => [
            ['photo' => UploadedFile::fake()->image('fuite-1.jpg', 640, 480), 'latitude' => 5.351, 'longitude' => -4.021],
            ['photo' => UploadedFile::fake()->image('fuite-2.jpg', 800, 600), 'latitude' => 5.351, 'longitude' => -4.021],
        ]], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($this->artisanA)->post("/api/v1/litiges/{$litigeId}/preuves", ['photos' => [
            ['photo' => UploadedFile::fake()->image('travaux.jpg', 1024, 768), 'latitude' => 5.351, 'longitude' => -4.021],
        ]], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('arbitrage', Litige::findOrFail($litigeId)->workflow_step);

        $this->step('   le client ne peut pas s\'arbitrer lui-même');
        $this->actingAs($this->client)
            ->putJson("/api/v1/litiges/{$litigeId}/arbitrage", ['decision' => 'client'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('admin');

        $this->step('Admin : arbitrage « mixte » → main d\'œuvre remboursée au client');
        $this->actingAs($this->admin)
            ->putJson("/api/v1/litiges/{$litigeId}/arbitrage", ['decision' => 'mixte', 'notes' => 'Raccords non conformes constatés sur photos.'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'resolu')
            ->assertJsonPath('data.resolutionPayload.refund_mo', 60000);

        $mission->refresh();
        $this->assertFalse((bool) $mission->funds_frozen);
        $this->assertSame(0, $this->artisanA->fresh()->wallet_mo);
        $this->assertDatabaseHas('transactions', [
            'mission_id' => $mission->id,
            'user_id' => $this->client->id,
            'type' => 'remboursement',
            'montant' => 60000,
        ]);
        $this->step('   60 000 FCFA remboursés au client, séquestre de la mission soldé');
    }

    // ─── 4. Commande de matériaux, préparation, livraison, course payée ───

    private function commandeLivree(): Order
    {
        $this->step('Client : ajoute une adresse de livraison, commande 4 sacs de ciment');
        $addressId = $this->actingAs($this->client)->postJson('/api/v1/addresses', [
            'label' => 'Maison',
            'recipient_name' => 'Kouamé Kouassi',
            'recipient_phone' => '+2250701000001',
            'address_line' => 'Cocody Angré 8e Tranche, villa 12',
            'city' => 'Abidjan',
        ])->assertCreated()->json('data.id');

        /** @var User $intrus */
        $intrus = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->step('   un autre client ne peut pas se faire livrer à cette adresse (anti-IDOR)');
        $this->actingAs($intrus)->postJson('/api/v1/orders', [
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'delivery',
            'address_id' => $addressId,
            'items' => [['supplier_product_id' => $this->ciment->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Adresse de livraison invalide.');

        $this->actingAs($this->client)->postJson('/api/v1/orders', [
            'supplier_id' => $this->supplier->id,
            'delivery_mode' => 'delivery',
            'address_id' => $addressId,
            'items' => [['supplier_product_id' => $this->ciment->id, 'quantity' => 4]],
        ])->assertCreated();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('pending', $order->status);

        $this->step('Quincaillerie : ne peut pas préparer une commande impayée');
        $this->actingAs($this->supplier)->postJson("/api/v1/orders/{$order->id}/prepared")->assertStatus(400);

        $this->step('Client : paiement Wave de la commande');
        $this->actingAs($this->client)
            ->postJson("/api/v1/payments/orders/{$order->id}/checkout", ['provider' => 'wave', 'phone' => $this->client->phone])
            ->assertOk();
        $payment = Transaction::where('type', 'acompte')->latest('id')->firstOrFail();
        $payments = app(PaymentService::class);
        $payments->confirmSimulatedPayment($payment);
        $payments->applyConfirmedPayment($payment->fresh());
        $this->assertSame('paid', $order->fresh()->status);
        $this->step("   commande #{$order->id} payée : {$order->total_amount} FCFA");

        $this->step('Quincaillerie : commande préparée → recherche d\'un livreur');
        $this->actingAs($this->supplier)->postJson("/api/v1/orders/{$order->id}/prepared")->assertOk();
        $this->assertSame('searching_driver', $order->fresh()->status);

        $this->step('Livreur : voit la course, l\'accepte (aucun code ne lui est transmis)');
        $available = $this->actingAs($this->driver)->getJson('/api/v1/deliveries/available')->assertOk();
        $this->assertContains($order->id, collect($available->json('data'))->pluck('id')->all());
        $accept = $this->actingAs($this->driver)->postJson("/api/v1/deliveries/{$order->id}/accept")->assertOk();
        $this->assertStringNotContainsString($order->fresh()->pickup_code, $accept->getContent());
        $this->assertSame('driver_assigned', $order->fresh()->status);

        $this->actingAs($this->driver)->postJson("/api/v1/orders/{$order->id}/location", [
            'latitude' => 5.3420, 'longitude' => -3.9850, 'speed_kmh' => 28, 'heading' => 90,
        ])->assertOk();

        $codes = Order::findOrFail($order->id)->makeVisible(['pickup_code', 'reception_code']);

        $this->step('Retrait en boutique : code dérivé du numéro de commande refusé, vrai code accepté');
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => "RET-{$order->id}"])
            ->assertStatus(400);
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/pickup", ['pickup_code' => $codes->pickup_code])
            ->assertOk();
        $this->assertSame('driver_picked_up', $order->fresh()->status);

        $this->step('Livraison chez le client avec son code de réception');
        $this->actingAs($this->driver)
            ->postJson("/api/v1/orders/{$order->id}/deliver", ['reception_code' => $codes->reception_code])
            ->assertOk();
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('a_payer', $order->delivery_fare_status);

        $this->step('Client : règle la course révélée à la livraison');
        $this->actingAs($this->client)
            ->postJson("/api/v1/payments/orders/{$order->id}/delivery-fare", ['provider' => 'wave', 'phone' => $this->client->phone])
            ->assertOk();
        $farePayment = Transaction::where('type', 'paiement_livraison')->latest('id')->firstOrFail();
        $payments->confirmSimulatedPayment($farePayment);
        $payments->applyConfirmedPayment($farePayment->fresh());

        $gain = (int) $this->driver->fresh()->wallet_mo;
        $this->assertGreaterThan(0, $gain, 'Le livreur est crédité de sa course.');
        $this->step("   course payée ({$farePayment->montant} FCFA), livreur crédité de {$gain} FCFA");

        $this->step('Client : évalue le livreur et la quincaillerie');
        $this->actingAs($this->client)->postJson('/api/v1/evaluations', [
            'order_id' => $order->id, 'evalue_id' => $this->driver->id, 'note' => 5, 'commentaire' => 'Livraison rapide.',
        ])->assertCreated();
        $this->actingAs($this->client)->postJson('/api/v1/evaluations', [
            'order_id' => $order->id, 'evalue_id' => $this->supplier->id, 'note' => 4, 'commentaire' => 'Ciment conforme.',
        ])->assertCreated();

        return $order;
    }

    // ─── 5. Décaissements : retrait du livreur et cash-out de la quincaillerie ───

    private function decaissements(): void
    {
        $gain = (int) $this->driver->fresh()->wallet_mo;

        $this->step("Livreur : demande le retrait de ses {$gain} FCFA (Wave) — réservé, pas encore débité");
        $this->actingAs($this->driver)->postJson('/api/v1/driver/cashouts', ['montant_brut' => $gain + 1, 'mode_retrait' => 'wave'])
            ->assertStatus(422);
        $this->actingAs($this->driver)->postJson('/api/v1/driver/cashouts', ['montant_brut' => $gain, 'mode_retrait' => 'wave'])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'en_attente');
        $this->assertSame($gain, (int) $this->driver->fresh()->wallet_mo);

        $this->step('Admin (backoffice) : verse le retrait du livreur');
        $cashout = DriverCashout::where('driver_id', $this->driver->id)->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/driver-cashouts/{$cashout->id}/pay")->assertSessionHas('success');
        $this->assertSame(DriverCashout::STATUT_COMPLETE, $cashout->fresh()->statut);
        $this->assertSame(0, (int) $this->driver->fresh()->wallet_mo);

        $solde = (int) $this->supplier->fresh()->wallet_materiaux;
        $this->assertGreaterThan(0, $solde, 'La quincaillerie a été créditée (J-Code + commande).');

        $this->step("Quincaillerie : demande le cash-out de ses {$solde} FCFA (Orange Money)");
        $this->actingAs($this->supplier)->postJson('/api/v1/supplier/cashouts', [
            'montant_brut' => $solde,
            'mode_retrait' => 'orange_money',
            'numero_mobile_money' => '+2250701000004',
        ])->assertCreated();
        $supplierCashout = SupplierCashout::where('supplier_id', $this->supplier->id)->latest('id')->firstOrFail();

        $this->step('Admin : approuve puis exécute le cash-out');
        $this->actingAs($this->admin)->post("/admin/cashouts/{$supplierCashout->id}/approve")->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/cashouts/{$supplierCashout->id}/complete")->assertRedirect();
        $this->assertSame('complete', (string) $supplierCashout->fresh()->statut);
        $this->step("   cash-out versé : {$supplierCashout->fresh()->montant_net} FCFA nets");
    }

    // ─── 6. Audits : la trésorerie doit tomber juste ───

    private function auditsDeTresorerie(): void
    {
        $this->step('Audit : jalons hybrides et doubles paiements');
        $this->assertSame(0, Artisan::call('prosartisan:reconcile-hybrid-jalons'));

        $this->step('Audit : grand livre en partie double (débits = crédits)');
        $this->assertSame(0, Artisan::call('ledger:verify-integrity'), Artisan::output());

        $this->step('Audit : réconciliation de la trésorerie (séquestres et soldes vs ledger)');
        $code = Artisan::call('prosartisan:reconcile-treasury');
        fwrite(STDERR, "\n".preg_replace('/^/m', '      ', trim(Artisan::output())));
        $this->assertSame(0, $code, 'La réconciliation de trésorerie signale un écart.');
    }
}
