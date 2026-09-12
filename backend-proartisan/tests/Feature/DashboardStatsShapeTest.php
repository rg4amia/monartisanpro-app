<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Forme JSON des statistiques du tableau de bord.
 *
 * `expenses_by_category` est construit par `pluck('total', 'category')`, donc
 * un tableau associatif PHP. Vide, il se sérialise en `[]` — un tableau JSON —
 * et non en `{}`. Le mobile le transtype en `Map` : pour tout client sans
 * mission, la conversion levait
 * « type 'List<dynamic>' is not a subtype of type 'Map<dynamic, dynamic>' ».
 *
 * L'exception interrompait la lecture du reste de la réponse : le classement
 * des fournisseurs, celui des livreurs et le rafraîchissement du score étaient
 * perdus en silence, sans que rien ne s'affiche à l'utilisateur.
 *
 * Le contrat est donc : cette clé est **toujours** un objet JSON, y compris
 * vide. C'est le cas d'un compte neuf, soit exactement le premier écran que
 * voit un nouveau client.
 */
class DashboardStatsShapeTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);
    }

    public function test_expenses_by_category_is_an_object_for_a_client_without_missions(): void
    {
        $response = $this->actingAs($this->client())->getJson('/api/v1/dashboard');

        $response->assertOk();

        // On inspecte le JSON brut : `json()` décode `[]` et `{}` tous deux en
        // tableau PHP vide, ce qui masquerait précisément la différence testée.
        $expenses = data_get($response->json(), 'data.expenses_by_category');

        $this->assertIsArray($expenses);
        $this->assertEmpty($expenses);
        $this->assertStringContainsString(
            '"expenses_by_category":{}',
            $response->getContent(),
            'Un client sans mission doit recevoir un objet vide, pas un tableau : '
                .'le mobile transtype cette clé en Map.',
        );
    }

    private function mission(User $client, string $status, string $category, int $amount): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => "Chantier {$category}",
            'status' => $status,
            'gemini_category' => $category,
            'montant_total' => $amount,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.0,
        ]);
    }

    public function test_expenses_by_category_keeps_its_object_shape_when_populated(): void
    {
        $client = $this->client();
        $this->mission($client, 'completed', 'Plomberie', 150000);

        $response = $this->actingAs($client)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.expenses_by_category.Plomberie', 150000);
        $this->assertStringNotContainsString(
            '"expenses_by_category":[',
            $response->getContent(),
        );
    }

    /**
     * Les cumuls doivent suivre les états réellement persistés.
     *
     * La migration FSM de juillet a réécrit `financee`/`en_cours`/`terminee`
     * en `funded_locked`/`in_progress`/`completed`, mais les filtres du
     * tableau de bord étaient restés en français. Aucune ligne ne pouvait
     * donc correspondre : `total_spent` et `active_missions_count` valaient
     * zéro pour tout le monde, et `expenses_by_category` restait vide — ce qui
     * rendait au passage le plantage de transtypage mobile systématique.
     */
    public function test_the_totals_count_missions_in_their_fsm_states(): void
    {
        $client = $this->client();

        $this->mission($client, 'funded_locked', 'Maçonnerie', 200000);
        $this->mission($client, 'in_progress', 'Plomberie', 50000);
        $this->mission($client, 'completed', 'Plomberie', 25000);
        // Un brouillon n'engage aucun séquestre : compté comme actif, jamais
        // dans les dépenses.
        $this->mission($client, 'draft', 'Peinture', 999000);

        $response = $this->actingAs($client)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.total_spent', 275000);
        // Actives : le séquestre bloqué, le chantier en cours et le brouillon.
        // La mission livrée en est exclue — elle n'est plus « en cours ».
        $response->assertJsonPath('data.active_missions_count', 3);
        $response->assertJsonPath('data.expenses_by_category.Plomberie', 75000);
        $response->assertJsonPath('data.expenses_by_category.Maçonnerie', 200000);
        $response->assertJsonMissingPath('data.expenses_by_category.Peinture');
    }

    public function test_the_artisan_earnings_follow_the_completed_state(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $client = $this->client();

        Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Chantier livré',
            'status' => 'completed',
            'montant_total' => 90000,
            'montant_materiaux' => 30000,
            'montant_mo' => 60000,
            'ratio_materiaux' => 0.3333,
        ]);

        $response = $this->actingAs($artisan)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.total_earnings', 60000);
    }

    /**
     * L'écran client affiche le véhicule sous le nom de chaque livreur, mais
     * la clé `vehicle` n'était jamais produite : le mobile la transtypait en
     * `String` et toute la section « Livreurs les mieux notés » devenait une
     * zone grise. Le défaut restait invisible tant que le classement était
     * vide — ce que garantissait le bogue de statuts.
     *
     * Le véhicule n'existe pas sur le compte livreur : il est porté par
     * chaque commande, d'où la classe la plus fréquente.
     */
    public function test_each_top_driver_carries_a_vehicle_label(): void
    {
        User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        $response = $this->actingAs($this->client())->getJson('/api/v1/dashboard');

        $response->assertOk();

        $drivers = $response->json('data.top_drivers');
        $this->assertNotEmpty($drivers, 'Le livreur créé doit apparaître au classement.');

        foreach ($drivers as $row) {
            $this->assertArrayHasKey('vehicle', $row);
            $this->assertNotNull($row['vehicle']);
            $this->assertContains($row['vehicle'], ['Moto', 'Voiture', 'Cargo']);
        }
    }

    /**
     * Une note jamais donnée ne doit pas être inventée.
     *
     * Les classements appliquaient `COALESCE(AVG(note), 5.0)` : un livreur
     * n'ayant jamais servi personne s'affichait à 5/5. Un palmarès « les mieux
     * notés » où chacun démarre au maximum ne classe rien et attribue une
     * réputation que nul n'a méritée. L'absence se dit désormais par `null`.
     */
    public function test_an_unrated_driver_has_no_rating_rather_than_a_perfect_one(): void
    {
        User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        $response = $this->actingAs($this->client())->getJson('/api/v1/dashboard');

        $response->assertOk();

        $driver = $response->json('data.top_drivers.0');

        $this->assertNull($driver['rating']);
        $this->assertSame(0, $driver['ratings_count']);
    }

    public function test_a_rated_driver_keeps_its_average(): void
    {
        $client = $this->client();
        $driver = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif']);

        foreach ([4, 5] as $note) {
            Evaluation::create([
                'evaluateur_id' => $client->id,
                'evalue_id' => $driver->id,
                'note' => $note,
            ]);
        }

        $response = $this->actingAs($client)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.top_drivers.0.rating', 4.5);
        $response->assertJsonPath('data.top_drivers.0.ratings_count', 2);
    }

    public function test_rated_actors_rank_before_unrated_ones(): void
    {
        $client = $this->client();

        User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'name' => 'Jamais evalue']);
        $rated = User::factory()->create(['role' => 'livreur', 'kyc_status' => 'actif', 'name' => 'Deja evalue']);

        // Une note de 3/5 vaut mieux qu'aucune note : classer un compte vierge
        // au-dessus reviendrait à récompenser l'absence d'historique.
        Evaluation::create([
            'evaluateur_id' => $client->id,
            'evalue_id' => $rated->id,
            'note' => 3,
        ]);

        $response = $this->actingAs($client)->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.top_drivers.0.name', 'Deja evalue');
        // Une moyenne ronde se sérialise en `3` et non `3.0` : le lecteur
        // mobile doit donc accepter aussi bien un entier qu'un décimal.
        $response->assertJsonPath('data.top_drivers.0.rating', 3);
        $response->assertJsonPath('data.top_drivers.1.rating', null);
    }

    public function test_the_other_dashboard_collections_stay_readable(): void
    {
        // Ces clés suivaient `expenses_by_category` dans le parcours mobile :
        // l'exception les emportait toutes. Leur présence garantit que la
        // réponse reste exploitable de bout en bout.
        $response = $this->actingAs($this->client())->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'accepted_devis_count',
                'refused_devis_count',
                'disputes_count',
                'expenses_by_category',
                'top_suppliers',
                'top_drivers',
            ],
        ]);
    }
}
