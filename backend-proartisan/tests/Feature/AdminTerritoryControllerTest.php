<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\ArtisanProfile;
use App\Models\Commune;
use App\Models\FournisseurAgree;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Sector;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminTerritoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Geo;
use Tests\TestCase;

class AdminTerritoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_cartography(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->get('/admin/cartographie')
            ->assertForbidden();
    }

    public function test_admin_can_view_cartography_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get('/admin/cartographie');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/cartography')
            ->has('territorySummary')
            ->has('territoryMatrix.national')
            ->has('territoryMatrix.unlocated')
            ->has('territoryMatrix.districts.abidjan')
            ->has('territoryMatrix.communes.cocody')
            ->has('districtsList')
            ->has('communesList')
        );
    }

    public function test_admin_can_fetch_territory_stats_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $cocody = Commune::create(['name' => 'Cocody', 'slug' => 'cocody', 'city' => 'Abidjan', 'country_code' => 'CI']);
        $plateau = Commune::create(['name' => 'Plateau', 'slug' => 'plateau', 'city' => 'Abidjan', 'country_code' => 'CI']);

        // Créer un artisan à Cocody
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'commune_id' => $cocody->id,
            'kyc_status' => 'actif',
            'score_prosartisan' => 850,
        ]);

        // Créer un client au Plateau
        $client = User::factory()->create([
            'role' => 'client',
            'commune_id' => $plateau->id,
            'kyc_status' => 'actif',
        ]);

        // Créer une mission complétée à Cocody
        Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'category' => 'Électricité',
            'description' => 'Rénovation tableau Cocody Angré',
            'client_address' => 'Cocody Angré 8ème tranche',
            'status' => 'completed',
            'montant_total' => 150000,
            'montant_materiaux' => 50000,
            'montant_mo' => 100000,
            'ratio_materiaux' => 0.3333,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?commune=cocody');

        $response->assertOk()
            ->assertJsonPath('summary.zone.type', 'commune')
            ->assertJsonPath('summary.actors.artisans', 1)
            ->assertJsonPath('summary.missions.total', 1)
            ->assertJsonPath('summary.missions.completed', 1)
            ->assertJsonPath('summary.missions.realization_rate', 100)
            ->assertJsonPath('summary.missions.total_volume_fcfa', 150000);
    }

    public function test_territory_breakdowns_cover_artisan_categories_supplier_sectors_and_cnmci(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cocody = Commune::create(['name' => 'Cocody', 'slug' => 'cocody', 'city' => 'Abidjan', 'country_code' => 'CI']);

        $electricite = Sector::create(['name' => 'Électricité']);
        $plomberie = Sector::create(['name' => 'Plomberie']);

        $artisanValide = User::factory()->create(['role' => 'artisan', 'commune_id' => $cocody->id, 'cnmci_status' => 'valide']);
        ArtisanProfile::create(['user_id' => $artisanValide->id, 'sector_id' => $electricite->id]);

        $artisanEnAttente = User::factory()->create(['role' => 'artisan', 'commune_id' => $cocody->id, 'cnmci_status' => 'en_attente']);
        ArtisanProfile::create(['user_id' => $artisanEnAttente->id, 'sector_id' => $electricite->id]);

        // Artisan sans profil du tout : doit être compté dans « Non renseigné ».
        User::factory()->create(['role' => 'artisan', 'commune_id' => $cocody->id, 'cnmci_status' => 'non_renseigne']);

        // Le hook d'auto-création de fournisseurs_agrees est désactivé pendant
        // les tests (User::booted()) : on crée le profil explicitement ici.
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'commune_id' => $cocody->id]);
        FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $fournisseur->id, 'nom_boutique' => 'Quincaillerie Plomberie', 'sector_id' => $plomberie->id, 'statut' => 'agree']);

        // Fournisseur sans secteur assigné : doit être compté dans « Non renseigné ».
        $fournisseurSansSecteur = User::factory()->create(['role' => 'fournisseur', 'commune_id' => $cocody->id]);
        FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $fournisseurSansSecteur->id, 'nom_boutique' => 'Quincaillerie Générale', 'statut' => 'agree']);

        $response = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?commune=cocody');

        $response->assertOk()
            ->assertJsonPath('breakdowns.artisan_categories.total', 3)
            ->assertJsonPath('breakdowns.cnmci.total_artisans', 3)
            ->assertJsonPath('breakdowns.cnmci.valide', 1)
            ->assertJsonPath('breakdowns.cnmci.en_attente', 1)
            ->assertJsonPath('breakdowns.cnmci.non_renseigne', 1)
            ->assertJsonPath('breakdowns.supplier_sectors.total', 2);

        $categories = collect($response->json('breakdowns.artisan_categories.items'))->keyBy('label');
        $this->assertSame(2, $categories['Électricité']['count']);
        $this->assertSame(1, $categories['Non renseigné']['count']);

        $sectors = collect($response->json('breakdowns.supplier_sectors.items'))->keyBy('label');
        $this->assertSame(1, $sectors['Plomberie']['count']);
        $this->assertSame(1, $sectors['Non renseigné']['count']);
    }

    public function test_district_matching_ignores_substring_false_positives(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Adresse contenant "Man" en sous-chaîne d'un mot sans rapport
        // (ex: "Allemagne") : ne doit PAS être comptée dans le district de Man (Tonkpi).
        $client1 = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan1 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        Mission::create([
            'client_id' => $client1->id,
            'artisan_id' => $artisan1->id,
            'category' => 'Peinture',
            'description' => 'Ravalement façade',
            'client_address' => 'Résidence Allemagne, Cocody',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 30000,
            'montant_mo' => 70000,
            'ratio_materiaux' => 0.3,
        ]);

        // Adresse réellement à Man (chef-lieu du district Tonkpi) : doit être comptée.
        $client2 = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan2 = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        Mission::create([
            'client_id' => $client2->id,
            'artisan_id' => $artisan2->id,
            'category' => 'Maçonnerie',
            'description' => 'Fondation chantier',
            'client_address' => 'Man centre-ville',
            'status' => 'completed',
            'montant_total' => 200000,
            'montant_materiaux' => 80000,
            'montant_mo' => 120000,
            'ratio_materiaux' => 0.4,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?district=tonkpi');

        $response->assertOk()
            ->assertJsonPath('summary.missions.total', 1)
            ->assertJsonPath('summary.missions.total_volume_fcfa', 200000);
    }

    public function test_zone_matrix_is_cached_and_invalidated_on_mission_change(): void
    {
        $service = app(AdminTerritoryService::class);

        $first = $service->getZoneMatrix();

        // Un second appel sans changement sous-jacent doit servir le résultat mis en
        // cache sans ré-exécuter les requêtes de la matrice (Règle d'Or 19).
        DB::enableQueryLog();
        $second = $service->getZoneMatrix();
        $queriesOnCacheHit = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertSame(0, $queriesOnCacheHit, 'Un appel avec cache chaud ne doit déclencher aucune requête SQL.');

        // La création d'une mission dans le district de Man (Tonkpi) invalide le cache
        // via l'observer déjà branché sur Mission (AdminDashboardCacheObserver) et doit
        // se refléter immédiatement, sans attendre le TTL.
        $this->mission(['client_address' => 'Man centre-ville', 'montant_total' => 50000]);

        $third = $service->getZoneMatrix();
        $this->assertSame($first['districts']['tonkpi']['missions_total'] + 1, $third['districts']['tonkpi']['missions_total']);
    }

    // ── Chantier 18 : filtres par type, matrice par zone, tableau et export ──

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mission(array $attributes = []): Mission
    {
        // Client et artisan créés seulement s'ils ne sont pas fournis : ils
        // n'ont pas de commune et seraient comptés « non renseignés ».
        $attributes['client_id'] ??= User::factory()->create(['role' => 'client'])->id;
        $attributes['artisan_id'] ??= User::factory()->create(['role' => 'artisan'])->id;

        return Mission::create(array_merge([
            'category' => 'Plomberie',
            'description' => 'Fuite',
            'status' => 'completed',
            'montant_total' => 100000,
            'montant_materiaux' => 40000,
            'montant_mo' => 60000,
            'ratio_materiaux' => 0.4,
        ], $attributes));
    }

    private function commune(string $name, string $slug, string $city = 'Abidjan'): Commune
    {
        return Commune::create(['name' => $name, 'slug' => $slug, 'city' => $city, 'country_code' => 'CI']);
    }

    /**
     * Un acteur de chaque type à Cocody, et une mission en litige.
     */
    private function seedCocody(): Commune
    {
        $cocody = $this->commune('Cocody', 'cocody');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Awa Cliente', 'commune_id' => $cocody->id]);
        $artisan = User::factory()->create(['role' => 'artisan', 'name' => 'Koffi Artisan', 'commune_id' => $cocody->id, 'kyc_status' => 'actif']);
        User::factory()->create(['role' => 'livreur', 'name' => 'Moussa Livreur', 'commune_id' => $cocody->id]);
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'name' => 'Sita Boutique', 'commune_id' => $cocody->id]);
        FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $fournisseur->id, 'nom_boutique' => 'Quincaillerie du Lycée', 'statut' => 'agree']);

        $this->mission(['client_id' => $client->id, 'artisan_id' => $artisan->id, 'status' => 'disputed', 'client_address' => 'Cocody Riviera 3']);

        return $cocody;
    }

    /**
     * Défaut corrigé : l'écran envoyait `artisan`, le service attendait
     * `artisans`, si bien que chaque onglet affichait tous les utilisateurs.
     */
    #[DataProvider('entityTypes')]
    public function test_chaque_filtre_de_type_ne_renvoie_que_son_type(string $filter, array $expectedTypes): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedCocody();

        $types = collect($this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?entity_type='.$filter)
            ->assertOk()
            ->json('entities.data'))->pluck('type')->unique()->sort()->values()->all();

        $this->assertSame($expectedTypes, $types);
    }

    public static function entityTypes(): array
    {
        return [
            'clients' => ['client', ['client']],
            'artisans' => ['artisan', ['artisan']],
            'livreurs' => ['livreur', ['livreur']],
            'quincailleries' => ['fournisseur', ['fournisseur']],
            'missions' => ['mission', ['litige']],
            'litiges' => ['litige', ['litige']],
            'ancien identifiant pluriel' => ['artisans', ['artisan']],
            'tous les acteurs' => ['all', ['artisan', 'client', 'fournisseur', 'livreur']],
        ];
    }

    public function test_un_filtre_inconnu_est_refuse(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/admin/cartographie/stats?entity_type=referents')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/admin/cartographie/stats?district=atlantide')->assertUnprocessable();
        $this->actingAs($admin)->getJson('/admin/cartographie/stats?period=7')->assertUnprocessable();
    }

    public function test_la_liste_de_tous_les_acteurs_suit_les_types_coches_et_exclut_les_administrateurs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedCocody();

        $types = fn (string $query) => collect($this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?'.$query)->assertOk()->json('entities.data'))
            ->pluck('type')->unique()->sort()->values()->all();

        $this->assertSame(['artisan', 'livreur'], $types('types=artisan,livreur'));
        $this->assertNotContains('admin', $types('entity_type=all'));
    }

    /**
     * Défaut corrigé : la recherche visait la colonne `location_address`, qui
     * n'existe pas (erreur SQL sur MariaDB), et l'adresse n'était jamais affichée.
     */
    public function test_la_recherche_de_mission_porte_sur_l_adresse_du_chantier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mission(['client_address' => 'Cocody Riviera 3', 'status' => 'in_progress']);
        $this->mission(['client_address' => 'Yopougon Maroc']);

        $response = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?entity_type=mission&search=Riviera')
            ->assertOk()
            ->assertJsonPath('entities.total', 1)
            ->assertJsonPath('entities.data.0.location', 'Cocody Riviera 3')
            // Statut libellé en français, clé technique conservée à part (Règle d'or 27).
            ->assertJsonPath('entities.data.0.status', 'in_progress');

        $this->assertNotSame('in_progress', $response->json('entities.data.0.status_label'));
    }

    public function test_un_acteur_sans_commune_est_compte_a_part_jamais_sous_abidjan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedCocody();
        User::factory()->create(['role' => 'artisan', 'name' => 'Sans Commune', 'commune_id' => null]);
        $ailleurs = $this->commune('Ouagadougou', 'ouagadougou', 'Ouagadougou');
        User::factory()->create(['role' => 'client', 'name' => 'Hors Référentiel', 'commune_id' => $ailleurs->id]);

        $response = $this->actingAs($admin)->getJson('/admin/cartographie/stats')->assertOk();
        $matrix = $response->json('matrix');

        $this->assertSame(1, $matrix['unlocated']['artisans']);
        $this->assertSame(1, $matrix['unlocated']['clients']);
        $this->assertSame(1, $matrix['districts']['abidjan']['artisans']);

        // La somme des districts et des non renseignés retrouve le total national.
        foreach (['clients', 'artisans', 'livreurs', 'fournisseurs', 'missions_total'] as $field) {
            $sum = array_sum(array_column($matrix['districts'], $field)) + $matrix['unlocated'][$field];
            $this->assertSame($matrix['national'][$field], $sum, "Total national incohérent pour {$field}");
        }

        $unlocated = $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?district='.AdminTerritoryService::UNLOCATED)
            ->assertOk()
            ->assertJsonPath('summary.zone.type', 'unlocated')
            ->assertJsonPath('summary.actors.total_actors', 2);

        $names = collect($unlocated->json('entities.data'))->pluck('title')->sort()->values()->all();
        $this->assertSame(['Hors Référentiel', 'Sans Commune'], $names);
        // Sans commune : aucune localisation supposée, jamais « Abidjan » par défaut.
        $sansCommune = collect($unlocated->json('entities.data'))->firstWhere('title', 'Sans Commune');
        $this->assertNull($sansCommune['location']);
        $this->assertNull($sansCommune['district']);
    }

    public function test_la_matrice_decompte_chaque_type_par_zone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cocody = $this->seedCocody();

        // Quincaillerie non agréée, livreur en course, client avec mission en cours.
        $nonAgree = User::factory()->create(['role' => 'fournisseur', 'commune_id' => $cocody->id]);
        FournisseurAgree::create(['position' => Geo::point(), 'user_id' => $nonAgree->id, 'nom_boutique' => 'En attente', 'statut' => 'en_attente']);

        $livreur = User::where('role', 'livreur')->firstOrFail();
        Order::create([
            'supplier_id' => $nonAgree->id, 'client_id' => User::where('role', 'client')->value('id'), 'driver_id' => $livreur->id,
            'status' => 'driver_picked_up', 'delivery_mode' => 'delivery', 'subtotal' => 25000, 'delivery_cost' => 3000,
            'platform_fee' => 500, 'total_amount' => 28500, 'pickup_code' => 'A1B2', 'reception_code' => 'C3D4', 'vehicle_class' => 'moto',
        ]);

        $client = User::where('role', 'client')->where('commune_id', $cocody->id)->firstOrFail();
        $this->mission([
            'client_id' => $client->id, 'artisan_id' => User::where('role', 'artisan')->value('id'),
            'status' => 'in_progress', 'client_address' => 'Cocody Angré', 'montant_total' => 250000,
        ]);

        $bouake = $this->commune('Bouaké', 'bouake', 'Bouaké');
        User::factory()->create(['role' => 'artisan', 'commune_id' => $bouake->id, 'kyc_status' => 'en_attente']);

        $matrix = $this->actingAs($admin)->getJson('/admin/cartographie/stats')->assertOk()->json('matrix');
        $row = $matrix['communes']['cocody'];

        $this->assertSame(1, $row['clients']);
        $this->assertSame(1, $row['clients_mission_active']);
        $this->assertSame(1, $row['artisans']);
        $this->assertSame(1, $row['artisans_kyc_actif']);
        $this->assertSame(1, $row['livreurs']);
        $this->assertSame(1, $row['livreurs_en_course']);
        $this->assertSame(2, $row['fournisseurs']);
        $this->assertSame(1, $row['fournisseurs_agrees']);
        $this->assertSame(2, $row['missions_total']);
        $this->assertSame(1, $row['missions_en_cours']);
        $this->assertSame(1, $row['litiges']);
        $this->assertSame(350000, $row['volume_fcfa']);

        $this->assertSame($row['artisans'], $matrix['districts']['abidjan']['artisans']);
        $this->assertSame(1, $matrix['districts']['gbeke']['artisans']);
        $this->assertSame(0, $matrix['districts']['gbeke']['artisans_kyc_actif']);

        // La synthèse de la zone reprend les mêmes chiffres, calculés réellement.
        $this->actingAs($admin)->getJson('/admin/cartographie/stats?commune=cocody')
            ->assertJsonPath('summary.actors.clients_with_active_missions', 1)
            ->assertJsonPath('summary.actors.livreurs_en_course', 1)
            ->assertJsonPath('summary.actors.fournisseurs_agrees', 1)
            ->assertJsonPath('summary.actors.artisans_kyc_percent', 100)
            // Aucune évaluation : la note vaut null, jamais 0/5 (Règle d'or 29).
            ->assertJsonPath('summary.reputation.avg_rating', null);
    }

    public function test_une_mission_n_est_comptee_que_dans_une_zone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $yopougon = $this->commune('Yopougon', 'yopougon');
        $client = User::factory()->create(['role' => 'client', 'commune_id' => $yopougon->id]);

        // Chantier à Cocody pour un client domicilié à Yopougon : l'adresse du chantier fait foi.
        $this->mission(['client_id' => $client->id, 'client_address' => 'Cocody Danga']);
        // Sans adresse exploitable : la commune du client situe la mission.
        $this->mission(['client_id' => $client->id, 'client_address' => null]);

        $matrix = $this->actingAs($admin)->getJson('/admin/cartographie/stats')->json('matrix');

        $this->assertSame(1, $matrix['communes']['cocody']['missions_total']);
        $this->assertSame(1, $matrix['communes']['yopougon']['missions_total']);
        $this->assertSame(2, $matrix['districts']['abidjan']['missions_total']);
        $this->assertSame(2, $matrix['national']['missions_total']);

        $this->actingAs($admin)->getJson('/admin/cartographie/stats?commune=cocody&entity_type=mission')
            ->assertJsonPath('entities.total', 1)
            ->assertJsonPath('entities.data.0.location', 'Cocody Danga');
    }

    public function test_les_filtres_de_statut_de_periode_et_de_kyc_s_appliquent_a_la_matrice_et_a_la_liste(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cocody = $this->seedCocody();
        User::factory()->create(['role' => 'artisan', 'commune_id' => $cocody->id, 'kyc_status' => 'en_attente']);
        $client = User::where('role', 'client')->firstOrFail();
        $artisan = User::where('role', 'artisan')->where('kyc_status', 'actif')->firstOrFail();
        $parties = ['client_id' => $client->id, 'artisan_id' => $artisan->id];
        $this->mission($parties + ['client_address' => 'Cocody Angré', 'status' => 'in_progress']);
        $ancienne = $this->mission($parties + ['client_address' => 'Cocody Mermoz', 'status' => 'completed']);
        Mission::whereKey($ancienne->id)->update(['created_at' => now()->subDays(60)]);

        $matrix = fn (string $query) => $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?'.$query)->assertOk()->json('matrix.communes.cocody');

        $this->assertSame(3, $matrix('')['missions_total']);
        $this->assertSame(1, $matrix('mission_status=en_cours')['missions_total']);
        $this->assertSame(1, $matrix('mission_status=litige')['missions_total']);
        $this->assertSame(2, $matrix('period=30')['missions_total']);
        $this->assertSame(3, $matrix('period=90')['missions_total']);

        $this->assertSame(2, $matrix('')['artisans']);
        $this->assertSame(1, $matrix('kyc=actif')['artisans']);
        $this->assertSame(1, $matrix('kyc=en_attente')['artisans']);

        $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?entity_type=mission&mission_status=en_cours')
            ->assertJsonPath('entities.total', 1)
            ->assertJsonPath('entities.data.0.status', 'in_progress');

        $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?entity_type=artisan&kyc=en_attente')
            ->assertJsonPath('entities.total', 1);
    }

    public function test_la_liste_detaille_chaque_type_d_acteur(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cocody = $this->seedCocody();
        $sector = Sector::create(['name' => 'Électricité']);
        $artisan = User::where('role', 'artisan')->firstOrFail();
        ArtisanProfile::create(['user_id' => $artisan->id, 'sector_id' => $sector->id]);

        $item = fn (string $type) => $this->actingAs($admin)
            ->getJson('/admin/cartographie/stats?entity_type='.$type)->assertOk()->json('entities.data.0');

        $this->assertSame('Électricité', $item('artisan')['detail']);
        $this->assertSame('Cocody', $item('artisan')['location']);
        $this->assertSame('Abidjan', $item('artisan')['district']);
        $this->assertSame('KYC actif', $item('artisan')['status_label']);
        $this->assertSame('Quincaillerie du Lycée — Agréée', $item('fournisseur')['detail']);
        $this->assertNull($item('client')['score_prosartisan']);
    }

    public function test_l_export_csv_de_la_synthese_et_du_detail_est_audite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedCocody();

        $synthese = $this->actingAs($admin)->get('/admin/cartographie/export?vue=synthese');
        $synthese->assertOk();
        $csv = $synthese->streamedContent();
        $this->assertStringContainsString('Zone;Clients;Artisans', $csv);
        $this->assertStringContainsString('Abidjan;1;1;1;1;0;1;1;0;0;1;1;100000;0', $csv);
        $this->assertStringContainsString('"Commune non renseignée"', $csv);
        $this->assertStringContainsString('"Total national"', $csv);

        $communes = $this->actingAs($admin)->get('/admin/cartographie/export?vue=synthese&district=abidjan')->streamedContent();
        $this->assertStringContainsString('Cocody;1;1', $communes);
        $this->assertStringContainsString('"Total Grand Abidjan"', $communes);

        $detail = $this->actingAs($admin)->get('/admin/cartographie/export?vue=detail&entity_type=fournisseur')->streamedContent();
        $this->assertStringContainsString('"Sita Boutique"', $detail);
        $this->assertStringNotContainsString('Koffi Artisan', $detail);

        $this->assertSame(3, AdminActivityLog::where('action', 'export.generated')->count());
    }

    public function test_l_export_exige_la_capacite_d_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        app(AdminPermissionService::class)->sync($admin, ['admin.territory.view'], User::factory()->create(['role' => 'admin']));

        $this->actingAs($admin)->get('/admin/cartographie/stats')->assertOk();
        $this->actingAs($admin)->get('/admin/cartographie/export')->assertForbidden();
    }
}
