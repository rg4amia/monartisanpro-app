<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\Models\MobileMoneyPayout;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use App\Services\DevisService;
use App\Services\LitigeService;
use App\Services\MissionLifecycleService;
use App\Services\WaveService;
use App\States\Mission\DisputedState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Chantier 19, compléments du 03/10/2026 :
 * - le seuil Référent s'applique à l'arbitrage d'un litige en faveur de l'artisan ;
 * - le backoffice affiche l'historique des états d'une mission, contrôle les
 *   historiques incomplets et les reconstitue.
 */
class Chantier19ReferentArbitrationAndHistoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private User $admin;

    private User $referent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $this->referent = User::factory()->create(['role' => 'referent', 'kyc_status' => 'actif']);

        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->andReturn(['id' => 'WAVE-TEST'])->byDefault();
        });
    }

    /**
     * Mission financée par le parcours réel, puis litige ouvert par le client.
     *
     * @return array{0: Mission, 1: Litige}
     */
    private function disputedMission(int $mainOeuvre): array
    {
        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Construction d\'une dépendance',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0,
            'client_latitude' => 5.3600,
            'client_longitude' => -4.0083,
        ]);

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $this->artisan->id,
            'materials_required' => false,
            'intervention_type_id' => 1,
            'commission_service_ratio' => 0.10,
            'lignes_json' => [['type' => 'mo', 'description' => 'Main d\'œuvre', 'montant' => $mainOeuvre]],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Fondations', 'montant' => intdiv($mainOeuvre, 2), 'date_cible' => now()->addDays(5)->toDateString()],
                ['ordre' => 2, 'description' => 'Élévation', 'montant' => $mainOeuvre - intdiv($mainOeuvre, 2), 'date_cible' => now()->addDays(10)->toDateString()],
            ],
            'statut' => 'soumis',
            'is_avenant' => false,
        ]);

        $payment = Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $this->client->id,
            'type' => 'acompte',
            'montant' => $devis->montant_total,
            'wallet_source' => 'client_mobile_money',
            'wallet_dest' => 'escrow_mission_'.$mission->id,
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'TXN-C19R-'.$mission->id,
            'metadata' => ['devis_id' => $devis->id],
        ]);

        app(DevisService::class)->accept($devis, $payment);

        $litige = Litige::create([
            'mission_id' => $mission->id, 'declencheur_id' => $this->client->id, 'type' => 'client',
            'motif' => 'Malfaçon', 'description' => 'Les fondations ne sont pas conformes au devis.',
            'statut' => 'ouvert',
        ]);

        app(MissionLifecycleService::class)->transition($mission->fresh(), DisputedState::class, $this->client, 'Litige ouvert');

        return [$mission->fresh(), $litige];
    }

    // ── Seuil Référent et arbitrage en faveur de l'artisan ───────────────

    public function test_au_dela_du_seuil_l_arbitrage_en_faveur_de_l_artisan_attend_la_visite_du_referent(): void
    {
        [$mission, $litige] = $this->disputedMission(3000000);
        $this->assertGreaterThan(2000000, (int) $mission->montant_total);

        try {
            app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'artisan']);
            $this->fail('L\'arbitrage en faveur de l\'artisan aurait dû être refusé avant la visite du Référent.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('visite du Référent', $e->errors()['decision'][0]);
        }

        $mission->refresh();
        $this->assertNotSame('completed', (string) $mission->status);
        $this->assertTrue($mission->referent_required);
        $this->assertSame('ouvert', $litige->fresh()->statut);
        $this->assertSame(0, MobileMoneyPayout::count());
        $this->assertTrue(Notification::where('user_id', $this->referent->id)->where('event_key', 'litige.visite_referent.referent')->exists());

        // Un second essai ne prévient pas les Référents une seconde fois.
        try {
            app(LitigeService::class)->arbitrate($this->admin, $litige->fresh(), ['decision' => 'artisan']);
        } catch (ValidationException) {
        }
        $this->assertSame(1, Notification::where('user_id', $this->referent->id)->where('event_key', 'litige.visite_referent.referent')->count());
    }

    public function test_au_dela_du_seuil_la_responsabilite_partagee_attend_aussi_la_visite_du_referent(): void
    {
        [$mission, $litige] = $this->disputedMission(3000000);

        try {
            app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'mixte', 'refund_mo' => 1000000]);
            $this->fail('La responsabilité partagée aurait dû être refusée avant la visite du Référent.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('responsabilité partagée', $e->errors()['decision'][0]);
        }

        $this->assertSame('disputed', (string) $mission->fresh()->status);
        $this->assertTrue($mission->fresh()->referent_required);
        $this->assertSame(0, MobileMoneyPayout::count());

        $mission->update(['referent_validated_at' => now(), 'referent_validated_by' => $this->referent->id]);
        app(LitigeService::class)->arbitrate($this->admin, $litige->fresh(), ['decision' => 'mixte', 'refund_mo' => 1000000]);

        $this->assertSame('completed', (string) $mission->fresh()->status);
    }

    public function test_une_fois_la_visite_enregistree_le_litige_se_tranche_en_faveur_de_l_artisan(): void
    {
        [$mission, $litige] = $this->disputedMission(3000000);
        $mission->update(['referent_validated_at' => now(), 'referent_validated_by' => $this->referent->id]);

        app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'artisan']);

        $this->assertSame('completed', (string) $mission->fresh()->status);
        $this->assertSame('resolu', $litige->fresh()->statut);
    }

    public function test_sous_le_seuil_l_arbitrage_en_faveur_de_l_artisan_n_attend_pas_le_referent(): void
    {
        [$mission, $litige] = $this->disputedMission(100000);

        app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'artisan']);

        $this->assertSame('completed', (string) $mission->fresh()->status);
    }

    public function test_au_dela_du_seuil_l_arbitrage_en_faveur_du_client_reste_possible_sans_visite(): void
    {
        [$mission, $litige] = $this->disputedMission(3000000);

        app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'client']);

        $this->assertSame('cancelled', (string) $mission->fresh()->status);
    }

    public function test_la_visite_du_referent_sur_une_mission_en_litige_ne_paie_aucune_etape(): void
    {
        [$mission, $litige] = $this->disputedMission(3000000);

        try {
            app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'artisan']);
        } catch (ValidationException) {
        }

        $jalon = Jalon::where('mission_id', $mission->id)->orderBy('ordre')->firstOrFail();
        $jalon->update(['statut' => 'valide']);

        // La mission en litige figure dans la liste du Référent.
        $this->actingAs($this->referent, 'sanctum')
            ->getJson('/api/v1/referent/missions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->referent, 'sanctum')
            ->post("/api/v1/missions/{$mission->id}/referent-validate", [
                'latitude' => 5.3600,
                'longitude' => -4.0083,
                'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.jalons_liberes', 0);

        $this->assertNotNull($mission->fresh()->referent_validated_at);
        $this->assertSame('valide', $jalon->fresh()->statut);
        $this->assertSame(0, MobileMoneyPayout::count());
        $this->assertTrue(Notification::where('user_id', $this->admin->id)->where('event_key', 'litige.visite_referent_faite.admin')->exists());

        // La visite faite, l'arbitrage aboutit.
        app(LitigeService::class)->arbitrate($this->admin, $litige->fresh(), ['decision' => 'artisan']);
        $this->assertSame('completed', (string) $mission->fresh()->status);
    }

    // ── Historique des états dans le backoffice ──────────────────────────

    public function test_le_backoffice_affiche_l_historique_d_une_mission_avec_ses_libelles(): void
    {
        [$mission, $litige] = $this->disputedMission(100000);
        app(LitigeService::class)->arbitrate($this->admin, $litige, ['decision' => 'artisan']);

        $response = $this->actingAs($this->admin)
            ->getJson("/admin/missions/{$mission->id}/historique")
            ->assertOk()
            ->assertJsonPath('mission_id', $mission->id)
            ->assertJsonPath('current_state', 'completed');

        $closing = collect($response->json('transitions'))->firstWhere('to_state', 'completed');
        $this->assertSame($this->admin->id, $closing['user']['id']);
        $this->assertSame("Litige arbitré en faveur de l'artisan", $closing['reason']);
        $this->assertFalse($closing['reconstituted']);
        $this->assertNotSame('completed', $closing['to_state_label']);
    }

    public function test_l_historique_du_backoffice_est_reserve_aux_administrateurs_habilites(): void
    {
        [$mission] = $this->disputedMission(100000);

        $this->getJson("/admin/missions/{$mission->id}/historique")->assertUnauthorized();
        $this->actingAs($this->client)->getJson("/admin/missions/{$mission->id}/historique")->assertForbidden();

        $restreint = User::factory()->create(['role' => 'admin']);
        app(AdminPermissionService::class)->sync($restreint, ['admin.users.view'], $this->admin);
        $this->actingAs($restreint)->getJson("/admin/missions/{$mission->id}/historique")->assertForbidden();
    }

    public function test_le_backoffice_controle_puis_reconstitue_les_historiques_incomplets(): void
    {
        // Mission clôturée avant le Chantier 19 : aucun historique.
        $ancienne = Mission::create([
            'client_id' => $this->client->id, 'artisan_id' => $this->artisan->id,
            'description' => 'Peinture', 'status' => 'in_progress',
            'montant_total' => 100000, 'montant_materiaux' => 0, 'montant_mo' => 100000, 'ratio_materiaux' => 0,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/admin/missions/historique/controle')
            ->assertOk()
            ->assertExactJson(['missions' => 1, 'incomplete' => 1, 'lines' => 1]);
        $this->assertSame(0, MissionStateTransition::where('mission_id', $ancienne->id)->count());

        $this->actingAs($this->admin)
            ->post('/admin/missions/historique/reconstituer')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, MissionStateTransition::where('mission_id', $ancienne->id)->count());
        $this->assertTrue(AdminActivityLog::where('action', 'mission.history.rebuilt')->where('admin_id', $this->admin->id)->exists());

        $this->actingAs($this->admin)
            ->getJson("/admin/missions/{$ancienne->id}/historique")
            ->assertOk()
            ->assertJsonPath('transitions.0.reconstituted', true)
            ->assertJsonPath('transitions.0.unknown_date', true)
            ->assertJsonPath('transitions.0.user', null);

        $this->actingAs($this->admin)
            ->getJson('/admin/missions/historique/controle')
            ->assertExactJson(['missions' => 1, 'incomplete' => 0, 'lines' => 0]);
    }

    public function test_la_reconstitution_exige_la_capacite_de_gestion_des_missions(): void
    {
        $lecteur = User::factory()->create(['role' => 'admin']);
        app(AdminPermissionService::class)->sync($lecteur, ['admin.missions.view'], $this->admin);

        $this->actingAs($lecteur)->getJson('/admin/missions/historique/controle')->assertOk();
        $this->actingAs($lecteur)->post('/admin/missions/historique/reconstituer')->assertForbidden();
    }
}
