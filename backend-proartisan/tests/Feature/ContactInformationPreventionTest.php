<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Mission;
use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactInformationPreventionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;
    private User $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $this->supplier = User::factory()->create([
            'role' => 'fournisseur',
            'kyc_status' => 'actif',
        ]);
    }

    public function test_it_blocks_mission_creation_if_description_contains_phone_number()
    {
        $sector = Sector::create(['name' => 'Bâtiment']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Plomberie']);

        $response = $this->actingAs($this->client)
            ->postJson('/api/v1/missions', [
                'description' => 'Bonjour, j’ai besoin d’un plombier. Mon numéro est le 0708091011 merci.',
                'sector_id' => $sector->id,
                'trade_id' => $trade->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_it_blocks_mission_creation_if_description_contains_email()
    {
        $sector = Sector::create(['name' => 'Bâtiment']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Plomberie']);

        $response = $this->actingAs($this->client)
            ->postJson('/api/v1/missions', [
                'description' => 'Besoin d’aide pour électricité. Contactez-moi sur test@example.com s’il vous plaît.',
                'sector_id' => $sector->id,
                'trade_id' => $trade->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_it_blocks_mission_creation_if_description_contains_whatsapp_keyword()
    {
        $sector = Sector::create(['name' => 'Bâtiment']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Plomberie']);

        $response = $this->actingAs($this->client)
            ->postJson('/api/v1/missions', [
                'description' => 'Besoin de carreler mon salon. Écrivez-moi sur WhatsApp pour s’arranger.',
                'sector_id' => $sector->id,
                'trade_id' => $trade->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_it_allows_mission_creation_with_prices_and_no_contact_info()
    {
        $sector = Sector::create(['name' => 'Bâtiment']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Plomberie']);

        $response = $this->actingAs($this->client)
            ->postJson('/api/v1/missions', [
                'description' => 'Je cherche un artisan pour refaire la peinture de mon salon de 45 mètres carrés.',
                'sector_id' => $sector->id,
                'trade_id' => $trade->id,
            ]);

        $response->assertStatus(201);
    }

    public function test_it_blocks_devis_creation_if_lines_contain_phone_number()
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Description de test pour la mission de carrelage',
            'status' => 'draft',
            'montant_total' => 50000,
            'montant_materiaux' => 30000,
            'montant_mo' => 20000,
            'ratio_materiaux' => 0.60,
        ]);

        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis", [
                'lignes' => [
                    [
                        'type' => 'mo',
                        'description' => 'Pose de carrelage (contact : 0102030405)',
                        'montant' => 20000,
                    ]
                ],
                'jalons' => [
                    [
                        'ordre' => 1,
                        'description' => 'Fin des travaux',
                        'montant' => 20000,
                        'date_cible' => now()->addDays(5)->toDateString(),
                    ]
                ]
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lignes.0.description']);
    }

    public function test_it_blocks_supplier_product_creation_if_description_contains_phone_number()
    {
        $response = $this->actingAs($this->supplier)
            ->postJson('/api/v1/supplier-products', [
                'name' => 'Ciment de qualité supérieure',
                'description' => 'Pour commander directement, appelez le +2250506070809',
                'unit_price' => 5000,
                'stock_quantity' => 100,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }
}
