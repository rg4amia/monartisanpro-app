<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Mission;
use App\Models\User;
use App\States\Mission\FundedLockedState;
use App\States\Mission\PendingFundingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionAddressWorkflowTest extends TestCase
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
            'phone' => '+2250700000101',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'phone' => '+2250700000202',
        ]);
        $this->artisan->artisanProfile()->create([
            'experience_years' => 5,
        ]);
    }

    public function test_client_can_create_mission_with_saved_address(): void
    {
        $address = Address::create([
            'user_id' => $this->client->id,
            'label' => 'Résidence Principale',
            'recipient_name' => 'Kouassi Jean',
            'recipient_phone' => '+2250700000101',
            'address_line' => 'Rue des Jardins, Immeuble A',
            'city' => 'Cocody',
            'region' => 'Abidjan',
            'country' => 'CI',
            'is_default' => true,
        ]);
        $address->setPosition(5.3599, -4.0083);

        $payload = [
            'artisan_id' => $this->artisan->id,
            'description' => 'Réparation complète de la tuyauterie de la salle de bain principale.',
            'address_id' => $address->id,
        ];

        $response = $this->actingAs($this->client)
            ->postJson('/api/v1/missions', $payload)
            ->assertCreated();

        $missionId = $response->json('data.id');
        $this->assertNotNull($missionId);

        $mission = Mission::find($missionId);
        $this->assertNotNull($mission);
        $this->assertEquals($address->id, $mission->address_id);
        $this->assertStringContainsString('Rue des Jardins', $mission->client_address);
        $this->assertEquals(5.3599, round((float) $mission->client_latitude, 4));
        $this->assertEquals(-4.0083, round((float) $mission->client_longitude, 4));
    }

    public function test_client_cannot_use_another_users_address_id(): void
    {
        $otherClient = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'phone' => '+2250700000999',
        ]);

        $otherAddress = Address::create([
            'user_id' => $otherClient->id,
            'label' => 'Villa Secrète',
            'recipient_name' => 'Autre Personne',
            'recipient_phone' => '+2250700000999',
            'address_line' => 'Zone 4, Rue du Canal',
            'city' => 'Marcory',
            'region' => 'Abidjan',
            'country' => 'CI',
            'is_default' => true,
        ]);

        $payload = [
            'artisan_id' => $this->artisan->id,
            'description' => 'Tentative d utilisation d une adresse tierce pour un chantier.',
            'address_id' => $otherAddress->id,
        ];

        $this->actingAs($this->client)
            ->postJson('/api/v1/missions', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('missions', [
            'description' => 'Tentative d utilisation d une adresse tierce pour un chantier.',
        ]);
    }

    public function test_deleting_address_keeps_mission_and_snapshot_intact(): void
    {
        $address = Address::create([
            'user_id' => $this->client->id,
            'label' => 'Chantier Temporaire',
            'recipient_name' => 'Kouassi Jean',
            'recipient_phone' => '+2250700000101',
            'address_line' => 'Carrefour Duncan',
            'city' => 'Attécoubé',
            'region' => 'Abidjan',
            'country' => 'CI',
            'is_default' => false,
        ]);
        $address->setPosition(5.3400, -4.0300);

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'address_id' => $address->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Remplacement de disjoncteur général et câblage tableau.',
            'client_address' => 'Carrefour Duncan, Attécoubé, Abidjan',
            'client_latitude' => 5.3400,
            'client_longitude' => -4.0300,
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.0000,
        ]);

        // Suppression de l'adresse par le client
        $this->actingAs($this->client)
            ->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertOk();

        // La mission doit toujours exister
        $mission->refresh();
        $this->assertNull($mission->address_id); // ON DELETE SET NULL
        $this->assertEquals('Carrefour Duncan, Attécoubé, Abidjan', $mission->client_address);
        $this->assertEquals(5.3400, round((float) $mission->client_latitude, 4));
        $this->assertEquals(-4.0300, round((float) $mission->client_longitude, 4));
    }

    public function test_artisan_cannot_see_address_id_before_funding(): void
    {
        $address = Address::create([
            'user_id' => $this->client->id,
            'label' => 'Maison',
            'recipient_name' => 'Kouassi Jean',
            'recipient_phone' => '+2250700000101',
            'address_line' => 'Boulevard Mitterrand',
            'city' => 'Cocody',
            'region' => 'Abidjan',
            'country' => 'CI',
            'is_default' => true,
        ]);

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'address_id' => $address->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Diagnostic de fuite sous évier et remplacement de siphon.',
            'client_address' => 'Boulevard Mitterrand, Cocody',
            'client_latitude' => 5.3500,
            'client_longitude' => -3.9800,
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.0000,
        ]);

        // Consultation par l'artisan en phase pré-financement
        $response = $this->actingAs($this->artisan)
            ->getJson("/api/v1/missions/{$mission->id}")
            ->assertOk();

        $response->assertJsonPath('data.addressId', null);
        $response->assertJsonPath('data.clientAddress', null);

        // Consultation par le client propriétaire
        $clientResponse = $this->actingAs($this->client)
            ->getJson("/api/v1/missions/{$mission->id}")
            ->assertOk();

        $clientResponse->assertJsonPath('data.addressId', $address->id);
        $clientResponse->assertJsonPath('data.clientAddress', 'Boulevard Mitterrand, Cocody');

        // Passage au statut financé via pending_funding
        $mission->status->transitionTo(PendingFundingState::class);
        $mission->status->transitionTo(FundedLockedState::class);

        // Dès que financée, l'artisan a accès à l'adresse du chantier
        $fundedResponse = $this->actingAs($this->artisan)
            ->getJson("/api/v1/missions/{$mission->id}")
            ->assertOk();

        $fundedResponse->assertJsonPath('data.addressId', $address->id);
        $fundedResponse->assertJsonPath('data.clientAddress', 'Boulevard Mitterrand, Cocody');
    }
}
