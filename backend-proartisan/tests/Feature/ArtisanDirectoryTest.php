<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\ArtisanAvailability;
use App\Models\ArtisanProfile;
use App\Models\Commune;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 15 — annuaire artisans : disponibilité validée avant publication,
 * présence pilotée depuis le backoffice.
 */
class ArtisanDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function makeArtisan(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'artisan', 'kyc_status' => 'actif', 'name' => 'Koffi Yao'], $attributes));
    }

    /** @return array<string, mixed> */
    private function declaration(array $overrides = []): array
    {
        return array_merge([
            'status' => 'disponible',
            'until_date' => null,
            'schedule' => [
                ['day' => 1, 'start' => '08:00', 'end' => '17:00'],
                ['day' => 2, 'start' => '08:00', 'end' => '17:00'],
                ['day' => 3, 'start' => '08:00', 'end' => '17:00'],
                ['day' => 6, 'start' => '08:00', 'end' => '12:00'],
            ],
            'night_work' => true,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function directoryEntry(User $artisan): ?array
    {
        $data = $this->getJson('/api/v1/vitrine/artisans')->assertOk()->json('data.data');

        return collect($data)->firstWhere('id', $artisan->id);
    }

    public function test_une_declaration_de_l_artisan_reste_invisible_avant_validation(): void
    {
        $artisan = $this->makeArtisan();

        $this->actingAs($artisan)
            ->postJson('/api/v1/artisan/availability', $this->declaration())
            ->assertCreated()
            ->assertJsonPath('data.pending.review_status', 'en_attente')
            ->assertJsonPath('data.published', null);

        // L'artisan figure dans l'annuaire, sans disponibilité publiée.
        $this->assertNull($this->directoryEntry($artisan)['availability']);

        $pending = ArtisanAvailability::sole();
        $this->actingAs($this->admin())
            ->post("/admin/annuaire-artisans/disponibilites/{$pending->id}/valider")
            ->assertSessionHasNoErrors();

        $entry = $this->directoryEntry($artisan);
        $this->assertSame('disponible', $entry['availability']['status']);
        $this->assertSame('Lundi au mercredi 08:00–17:00 · Samedi 08:00–12:00', $entry['availability']['schedule_summary']);
        $this->assertTrue($entry['availability']['night_work']);
        $this->assertSame('Disponibilité publiée', Notification::where('user_id', $artisan->id)->sole()->title);
        $this->assertTrue(AdminActivityLog::where('action', 'directory.availability.approved')->exists());
    }

    public function test_la_version_publiee_reste_affichee_pendant_la_revue_puis_apres_un_refus(): void
    {
        $artisan = $this->makeArtisan();
        ArtisanAvailability::create(['user_id' => $artisan->id, 'status' => 'disponible', 'schedule_json' => [], 'review_status' => 'validee', 'reviewed_at' => now()]);

        $this->actingAs($artisan)
            ->postJson('/api/v1/artisan/availability', $this->declaration(['status' => 'conge', 'until_date' => now()->addDays(10)->toDateString()]))
            ->assertCreated();
        $this->assertSame('disponible', $this->directoryEntry($artisan)['availability']['status']);

        $pending = ArtisanAvailability::where('review_status', 'en_attente')->sole();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/admin/annuaire-artisans/disponibilites/{$pending->id}/refuser", ['reason' => 'x'])
            ->assertSessionHasErrors(['reason']);
        $this->actingAs($admin)->post("/admin/annuaire-artisans/disponibilites/{$pending->id}/refuser", ['reason' => 'Date de retour incohérente'])
            ->assertSessionHasNoErrors();

        $this->assertSame('disponible', $this->directoryEntry($artisan)['availability']['status']);
        $this->assertSame('refusee', $pending->fresh()->review_status);
        $this->assertStringContainsString('Date de retour incohérente', Notification::where('user_id', $artisan->id)->sole()->body);

        $this->actingAs($artisan)->getJson('/api/v1/artisan/availability')
            ->assertJsonPath('data.last_rejected.rejection_reason', 'Date de retour incohérente')
            ->assertJsonPath('data.pending', null);

        // Une décision déjà prise ne se rejoue pas.
        $this->actingAs($admin)->post("/admin/annuaire-artisans/disponibilites/{$pending->id}/valider")->assertSessionHasErrors(['availability']);
    }

    public function test_une_nouvelle_declaration_remplace_celle_en_attente(): void
    {
        $artisan = $this->makeArtisan();

        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', $this->declaration())->assertCreated();
        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', $this->declaration(['night_work' => false]))->assertCreated();

        $this->assertSame(['remplacee', 'en_attente'], ArtisanAvailability::orderBy('id')->pluck('review_status')->all());
    }

    public function test_une_declaration_invalide_est_refusee(): void
    {
        $artisan = $this->makeArtisan();

        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', ['status' => 'occupe'])
            ->assertStatus(422)->assertJsonValidationErrors(['until_date']);
        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', ['status' => 'conge', 'until_date' => now()->subDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['until_date']);
        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', $this->declaration(['schedule' => [['day' => 1, 'start' => '17:00', 'end' => '08:00']]]))
            ->assertStatus(422)->assertJsonValidationErrors(['schedule.0']);
        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', $this->declaration(['schedule' => [
            ['day' => 1, 'start' => '08:00', 'end' => '12:00'],
            ['day' => 1, 'start' => '11:00', 'end' => '15:00'],
        ]]))->assertStatus(422)->assertJsonValidationErrors(['schedule.1']);

        $this->assertSame(0, ArtisanAvailability::count());
    }

    public function test_seul_un_artisan_declare_sa_disponibilite(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($client)->postJson('/api/v1/artisan/availability', $this->declaration())->assertForbidden();
        $this->actingAs($client)->getJson('/api/v1/artisan/availability')->assertForbidden();
        $this->assertSame(0, ArtisanAvailability::count());
    }

    public function test_la_saisie_de_l_administrateur_est_publiee_directement(): void
    {
        $artisan = $this->makeArtisan();
        $this->actingAs($artisan)->postJson('/api/v1/artisan/availability', $this->declaration())->assertCreated();

        $this->actingAs($this->admin())
            ->put("/admin/annuaire-artisans/{$artisan->id}/disponibilite", $this->declaration(['status' => 'occupe', 'until_date' => now()->addDays(3)->toDateString(), 'schedule' => []]))
            ->assertSessionHasNoErrors();

        $entry = $this->directoryEntry($artisan);
        $this->assertSame('occupe', $entry['availability']['status']);
        $this->assertStringStartsWith('Occupé jusqu\'au ', $entry['availability']['label']);
        // La déclaration en attente de l'artisan est remplacée par la saisie de l'administrateur.
        $this->assertSame(0, ArtisanAvailability::where('review_status', 'en_attente')->count());
        $this->assertTrue(AdminActivityLog::where('action', 'directory.availability.admin_set')->exists());
    }

    public function test_un_statut_occupe_echu_s_affiche_disponible(): void
    {
        $artisan = $this->makeArtisan();
        ArtisanAvailability::create([
            'user_id' => $artisan->id, 'status' => 'occupe', 'until_date' => now()->subDays(2)->toDateString(),
            'schedule_json' => [], 'review_status' => 'validee', 'reviewed_at' => now()->subWeek(),
        ]);
        $busy = $this->makeArtisan(['name' => 'Awa Touré']);
        ArtisanAvailability::create([
            'user_id' => $busy->id, 'status' => 'conge', 'until_date' => now()->addDays(5)->toDateString(),
            'schedule_json' => [], 'review_status' => 'validee', 'reviewed_at' => now(),
        ]);

        $this->assertSame('Disponible', $this->directoryEntry($artisan)['availability']['label']);

        $ids = collect($this->getJson('/api/v1/vitrine/artisans?disponible=1')->json('data.data'))->pluck('id')->all();
        $this->assertSame([$artisan->id], $ids);
    }

    public function test_un_artisan_desactive_disparait_de_l_annuaire_puis_revient(): void
    {
        $artisan = $this->makeArtisan();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/annuaire-artisans/{$artisan->id}/retirer", [])->assertSessionHasErrors(['reason']);
        $this->actingAs($admin)->post("/admin/annuaire-artisans/{$artisan->id}/retirer", ['reason' => 'Photo de profil inappropriée'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->directoryEntry($artisan));
        $this->getJson("/api/v1/vitrine/artisans/{$artisan->id}")->assertNotFound();
        $this->getJson('/api/v1/vitrine/artisans-stars')->assertJsonMissing(['id' => $artisan->id]);
        // Le compte n'est pas touché.
        $this->assertSame('actif', $artisan->fresh()->kyc_status);
        $this->assertSame('Photo de profil inappropriée', AdminActivityLog::where('action', 'directory.artisan.hidden')->sole()->context['motif']);

        $this->actingAs($admin)->post("/admin/annuaire-artisans/{$artisan->id}/publier")->assertSessionHasNoErrors();
        $this->assertNotNull($this->directoryEntry($artisan));
        $this->assertSame(['Fiche retirée de l\'annuaire', 'Fiche de nouveau visible'], Notification::where('user_id', $artisan->id)->orderBy('id')->pluck('title')->all());
    }

    public function test_l_annuaire_exclut_kyc_inactif_suspendus_et_anonymises(): void
    {
        $kept = $this->makeArtisan();
        $this->makeArtisan(['kyc_status' => 'en_attente']);
        $this->makeArtisan(['account_status' => 'suspendu']);
        $this->makeArtisan(['anonymized_at' => now()]);
        User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $ids = collect($this->getJson('/api/v1/vitrine/artisans')->json('data.data'))->pluck('id')->all();
        $this->assertSame([$kept->id], $ids);
    }

    public function test_la_recherche_par_commune_et_metier_fonctionne_et_n_expose_aucun_telephone(): void
    {
        $cocody = Commune::create(['name' => 'Cocody', 'slug' => 'cocody', 'city' => 'Abidjan', 'country_code' => 'CI']);
        $yopougon = Commune::create(['name' => 'Yopougon', 'slug' => 'yopougon', 'city' => 'Abidjan', 'country_code' => 'CI']);
        $trade = Trade::query()->first() ?? Trade::create(['name' => 'Plombier sanitaire', 'sector_id' => Sector::create(['name' => 'Bâtiment'])->id]);
        $plumber = $this->makeArtisan(['commune_id' => $cocody->id]);
        ArtisanProfile::create(['user_id' => $plumber->id, 'trade_id' => $trade->id]);
        $this->makeArtisan(['commune_id' => $yopougon->id, 'name' => 'Autre artisan']);

        $response = $this->getJson('/api/v1/vitrine/artisans?ville=Coco')->assertOk();
        $this->assertSame([$plumber->id], collect($response->json('data.data'))->pluck('id')->all());
        $response->assertJsonPath('data.data.0.city', 'Cocody')
            ->assertJsonPath('data.data.0.trade', $trade->name)
            ->assertJsonMissingPath('data.data.0.phone');

        $ids = collect($this->getJson('/api/v1/vitrine/artisans?metier='.urlencode($trade->name))->json('data.data'))->pluck('id')->all();
        $this->assertSame([$plumber->id], $ids);
    }

    public function test_l_onglet_exige_la_capacite_et_liste_les_disponibilites_en_attente(): void
    {
        $this->assertTrue(Permission::where('name', 'admin.directory.manage')->exists());

        $restricted = $this->admin();
        $permissionId = Permission::where('name', 'admin.faq.manage')->value('id');
        DB::table('admin_permission_user')->insert(['user_id' => $restricted->id, 'permission_id' => $permissionId, 'created_at' => now()]);
        $this->actingAs($restricted)->get('/admin/annuaire-artisans')->assertForbidden();

        $waiting = $this->makeArtisan(['name' => 'Zoé Attente']);
        $this->makeArtisan(['name' => 'Aya Sans demande']);
        ArtisanAvailability::create(['user_id' => $waiting->id, 'status' => 'disponible', 'schedule_json' => [], 'review_status' => 'en_attente', 'submitted_by' => $waiting->id]);

        $this->actingAs($this->admin())
            ->get('/admin/annuaire-artisans')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/artisan-directory')
                ->where('directoryStats.pending', 1)
                ->where('directoryStats.published', 2)
                // En attente d'abord, malgré l'ordre alphabétique.
                ->where('directoryArtisans.data.0.name', 'Zoé Attente')
                ->where('directoryArtisans.data.0.pending.review_status', 'en_attente'));
    }
}
