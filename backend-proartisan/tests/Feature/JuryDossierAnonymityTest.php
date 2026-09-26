<?php

namespace Tests\Feature;

use App\Models\JuryReview;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\States\Mission\DisputedState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier 12 — anonymisation bilatérale : le juré ne connaît pas l'identité
 * des parties. Il instruit le dossier par l'espace juré (`/jury/dossiers`),
 * jamais par la fiche litige des parties, qui expose noms et téléphones.
 */
class JuryDossierAnonymityTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private User $jure;

    private Litige $litige;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
            'name' => 'Awa Client Identifiable',
            'phone' => '+2250700000011',
        ]);
        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'name' => 'Koffi Artisan Identifiable',
            'phone' => '+2250700000012',
        ]);
        $this->jure = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => 900,
        ]);

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Carrelage salle de bain',
            'status' => DisputedState::class,
            'montant_total' => 150000,
            'montant_mo' => 100000,
            'montant_materiaux' => 50000,
            'ratio_materiaux' => 0.3333,
        ]);

        $this->litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $this->client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Carreaux décollés une semaine après la pose.',
            'statut' => 'ouvert',
            'workflow_step' => 'jury',
        ]);

        JuryReview::create([
            'litige_id' => $this->litige->id,
            'jure_id' => $this->jure->id,
            'compensation' => 5000,
            'status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);
    }

    private function assertNoPartyIdentity(string $json): void
    {
        foreach ([$this->client, $this->artisan] as $party) {
            $this->assertStringNotContainsString($party->name, $json);
            $this->assertStringNotContainsString($party->phone, $json);
        }
    }

    public function test_un_jure_n_accede_pas_a_la_fiche_litige_des_parties(): void
    {
        $this->actingAs($this->jure)
            ->getJson("/api/v1/litiges/{$this->litige->id}")
            ->assertForbidden();
    }

    public function test_les_parties_accedent_toujours_a_leur_fiche_litige(): void
    {
        $this->actingAs($this->client)
            ->getJson("/api/v1/litiges/{$this->litige->id}")
            ->assertOk()
            ->assertJsonPath('data.parties.artisan.name', $this->artisan->name);
    }

    public function test_la_liste_des_dossiers_du_jure_est_anonyme(): void
    {
        $response = $this->actingAs($this->jure)
            ->getJson('/api/v1/jury/dossiers')
            ->assertOk()
            ->assertJsonPath('data.data.0.litige_id', $this->litige->id)
            ->assertJsonPath('data.data.0.dossier.motif', 'malfaçon');

        $this->assertNoPartyIdentity($response->getContent());
    }

    public function test_le_dossier_du_jure_est_anonyme_et_complet(): void
    {
        $response = $this->actingAs($this->jure)
            ->getJson("/api/v1/jury/dossiers/{$this->litige->id}")
            ->assertOk()
            ->assertJsonPath('data.litige.motif', 'malfaçon')
            ->assertJsonPath('data.litige.description', 'Carreaux décollés une semaine après la pose.')
            ->assertJsonPath('data.litige.ouvert_par', 'client');

        $this->assertNoPartyIdentity($response->getContent());
    }

    public function test_un_non_jure_ne_voit_pas_le_dossier(): void
    {
        $other = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $this->actingAs($other)
            ->getJson("/api/v1/jury/dossiers/{$this->litige->id}")
            ->assertNotFound();
    }

    public function test_le_jure_vote_depuis_son_espace(): void
    {
        $this->actingAs($this->jure)
            ->postJson("/api/v1/jury/dossiers/{$this->litige->id}/vote", [
                'verdict' => 'RESPONSABILITE_PARTAGEE',
                'split_artisan_percentage' => 40,
                'technical_comment' => 'Colle inadaptée, support non préparé.',
            ])
            ->assertOk()
            ->assertJsonPath('data.verdict', 'RESPONSABILITE_PARTAGEE');

        $review = JuryReview::where('jure_id', $this->jure->id)->first();
        $this->assertSame('voted', $review->status);
        $this->assertSame(40, (int) $review->split_artisan_percentage);
    }
}
