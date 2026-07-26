<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $artisan;

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
    }

    public function test_client_cannot_request_devis_suggestion()
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Bonjour, j’ai besoin d’un plombier pour réparer une fuite d’eau dans ma douche.',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.00,
        ]);

        $response = $this->actingAs($this->client)
            ->getJson("/api/v1/missions/{$mission->id}/devis/suggest");

        $response->assertStatus(403);
    }

    public function test_artisan_receives_balanced_devis_suggestion()
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Bonjour, j’ai besoin de peindre tout mon salon de 50m2.',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.00,
        ]);

        $response = $this->actingAs($this->artisan)
            ->getJson("/api/v1/missions/{$mission->id}/devis/suggest");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'lignes' => [
                    '*' => ['type', 'description', 'montant', 'source']
                ],
                'jalons' => [
                    '*' => ['ordre', 'description', 'montant', 'date_cible']
                ]
            ]
        ]);

        // Vérifier l'équilibrage des montants
        $data = $response->json('data');
        $totalLignes = collect($data['lignes'])->sum('montant');
        $totalJalons = collect($data['jalons'])->sum('montant');

        $this->assertEquals($totalLignes, $totalJalons);
        $this->assertGreaterThan(0, $totalLignes);
    }
}
