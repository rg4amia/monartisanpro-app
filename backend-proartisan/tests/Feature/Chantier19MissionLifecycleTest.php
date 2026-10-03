<?php

namespace Tests\Feature;

use App\Enums\WalletType;
use App\Models\AdminActivityLog;
use App\Models\Devis;
use App\Models\Jalon;
use App\Models\JCode;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\Models\MobileMoneyPayout;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DevisService;
use App\Services\MissionHistoryBackfillService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Chantier 19 — cycle de vie des missions : historique complet, validation
 * finale, annulation avec pénalité, délai de réponse de l'artisan,
 * reconstitution de l'historique.
 */
class Chantier19MissionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->andReturn(['id' => 'WAVE-TEST'])->byDefault();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mission(array $attributes = []): Mission
    {
        return Mission::create(array_merge([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Réfection complète de la salle de bain',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0,
        ], $attributes));
    }

    /**
     * Mission financée par le parcours réel : devis soumis, paiement confirmé, devis accepté.
     */
    private function fundedMission(int $mainOeuvre = 100000): Mission
    {
        $mission = $this->mission();

        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $this->artisan->id,
            'materials_required' => false,
            'intervention_type_id' => 1,
            'commission_service_ratio' => 0.10,
            'lignes_json' => [['type' => 'mo', 'description' => 'Main d\'œuvre', 'montant' => $mainOeuvre]],
            'jalons_json' => [
                ['ordre' => 1, 'description' => 'Dépose', 'montant' => intdiv($mainOeuvre, 2), 'date_cible' => now()->addDays(5)->toDateString()],
                ['ordre' => 2, 'description' => 'Pose', 'montant' => $mainOeuvre - intdiv($mainOeuvre, 2), 'date_cible' => now()->addDays(10)->toDateString()],
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
            'reference_externe' => 'TXN-C19-'.$mission->id,
            'metadata' => ['devis_id' => $devis->id],
        ]);

        app(DevisService::class)->accept($devis, $payment);

        return $mission->fresh();
    }

    private function history(Mission $mission): array
    {
        return MissionStateTransition::where('mission_id', $mission->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => "{$t->from_state}>{$t->to_state}")
            ->all();
    }

    private function notified(User $user, string $event): bool
    {
        return Notification::where('user_id', $user->id)->where('event_key', $event)->exists();
    }

    // ── Lot 1 : machine à états et historique ────────────────────────────

    public function test_le_financement_passe_par_la_machine_a_etats_et_figure_dans_l_historique(): void
    {
        $mission = $this->fundedMission();

        $this->assertSame('funded_locked', (string) $mission->status);
        $this->assertSame(['draft>pending_funding', 'pending_funding>funded_locked'], $this->history($mission));

        $line = MissionStateTransition::where('mission_id', $mission->id)->where('to_state', 'funded_locked')->sole();
        $this->assertSame($this->client->id, $line->user_id);
        $this->assertSame('Acompte confirmé', $line->reason);
        $this->assertArrayHasKey('transaction_id', $line->metadata_json);
    }

    public function test_une_mission_ne_peut_pas_etre_financee_sans_devis_accepte_ni_paiement_confirme(): void
    {
        $mission = $this->mission(['status' => 'pending_funding']);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/missions/{$mission->id}/status", ['status' => 'funded_locked', 'reason' => 'Financement forcé sans paiement'])
            ->assertStatus(422)
            ->assertJsonPath('message', "Financement impossible : la mission #{$mission->id} n'a pas de devis accepté.");

        $this->assertSame('pending_funding', (string) $mission->fresh()->status);
    }

    public function test_un_forcage_administrateur_refuse_repond_422_en_francais(): void
    {
        $mission = $this->mission();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/missions/{$mission->id}/status", ['status' => 'completed', 'reason' => 'Clôture forcée par erreur'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Une mission « Brouillon » ne peut pas passer à « Terminée ».');

        $this->assertSame('draft', (string) $mission->fresh()->status);
    }

    public function test_un_forcage_administrateur_accepte_est_trace_avec_son_auteur_et_son_motif(): void
    {
        $mission = $this->mission();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/missions/{$mission->id}/status", ['status' => 'cancelled', 'reason' => 'Demande en double, annulée à la demande du client'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.statusLabel', 'Annulée');

        $line = MissionStateTransition::where('mission_id', $mission->id)->sole();
        $this->assertSame($this->admin->id, $line->user_id);
        $this->assertSame('Demande en double, annulée à la demande du client', $line->reason);
        $this->assertTrue($line->metadata_json['force_admin']);
        $this->assertSame(1, AdminActivityLog::where('action', 'mission.status.forced')->count());
    }

    public function test_le_motif_n_est_plus_lu_dans_la_requete(): void
    {
        $mission = $this->mission(['status' => 'pending_artisan_acceptance']);

        $this->actingAs($this->artisan, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/accept-request", ['reason' => 'texte glissé par l\'appelant'])
            ->assertOk();

        $this->assertSame('Demande de devis acceptée', MissionStateTransition::where('mission_id', $mission->id)->sole()->reason);
    }

    // ── Lot 2 : validation finale ────────────────────────────────────────

    private function payAllSteps(Mission $mission): void
    {
        foreach ($mission->jalons()->orderBy('ordre')->get() as $jalon) {
            $jalon->update(['statut' => 'paye', 'valide_at' => now(), 'paye_at' => now()]);
            // Reproduit l'effet de la dernière étape payée sans rejouer l'OTP.
            Jalon::whereKey($jalon->id)->update(['statut' => 'valide']);
            app(WalletService::class)->releaseJalon($jalon->fresh());
        }
    }

    public function test_la_derniere_etape_payee_met_la_mission_en_attente_de_validation_finale(): void
    {
        $mission = $this->fundedMission();
        $this->payAllSteps($mission);

        $mission->refresh();
        $this->assertSame('pending_approval', (string) $mission->status);
        $this->assertNotNull($mission->completion_requested_at);
        $this->assertTrue($this->notified($this->client, 'mission.validation_finale.client'));
        $this->assertContains('in_progress>pending_approval', $this->history($mission));
    }

    public function test_seul_le_client_valide_la_fin_du_chantier(): void
    {
        $mission = $this->mission(['status' => 'pending_approval', 'completion_requested_at' => now()]);
        $tiers = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($this->artisan, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/approve-completion")->assertForbidden();
        $this->actingAs($tiers, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/approve-completion")->assertForbidden();
        $this->assertSame('pending_approval', (string) $mission->fresh()->status);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/approve-completion")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertTrue($this->notified($this->artisan, 'mission.cloturee.artisan'));
        $line = MissionStateTransition::where('mission_id', $mission->id)->sole();
        $this->assertSame($this->client->id, $line->user_id);

        // Seconde validation : sans effet, sans erreur.
        $this->actingAs($this->client, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/approve-completion")->assertOk();
        $this->assertSame(1, MissionStateTransition::where('mission_id', $mission->id)->count());
    }

    public function test_la_validation_finale_est_refusee_hors_attente_de_validation(): void
    {
        $mission = $this->mission(['status' => 'in_progress']);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/approve-completion")
            ->assertStatus(422);

        $this->assertSame('in_progress', (string) $mission->fresh()->status);
    }

    public function test_sans_reponse_du_client_la_mission_est_cloturee_apres_72_heures(): void
    {
        $recente = $this->mission(['status' => 'pending_approval', 'completion_requested_at' => now()->subHours(71)]);
        $echue = $this->mission(['status' => 'pending_approval', 'completion_requested_at' => now()->subHours(73)]);

        $this->artisan('missions:auto-approve-completion')->assertSuccessful();

        $this->assertSame('pending_approval', (string) $recente->fresh()->status);
        $this->assertSame('completed', (string) $echue->fresh()->status);

        $line = MissionStateTransition::where('mission_id', $echue->id)->sole();
        $this->assertNull($line->user_id);
        $this->assertStringContainsString('sans réponse du client', $line->reason);
        $this->assertTrue($this->notified($this->client, 'mission.cloturee_auto.client'));
    }

    public function test_le_delai_de_validation_finale_se_regle(): void
    {
        Setting::updateOrCreate(['key' => 'mission_final_approval_hours'], ['value' => '24', 'type' => 'integer', 'group' => 'missions']);
        $mission = $this->mission(['status' => 'pending_approval', 'completion_requested_at' => now()->subHours(25)]);

        $this->artisan('missions:auto-approve-completion')->assertSuccessful();

        $this->assertSame('completed', (string) $mission->fresh()->status);
    }

    public function test_la_cloture_automatique_respecte_le_seuil_referent(): void
    {
        $mission = $this->mission([
            'status' => 'pending_approval',
            'completion_requested_at' => now()->subHours(80),
            'montant_total' => 3000000,
        ]);

        $this->artisan('missions:auto-approve-completion')->assertSuccessful();

        $this->assertSame('pending_approval', (string) $mission->fresh()->status);

        $mission->update(['referent_validated_at' => now()]);
        $this->artisan('missions:auto-approve-completion')->assertSuccessful();
        $this->assertSame('completed', (string) $mission->fresh()->status);
    }

    // ── Lot 3 : annulation ───────────────────────────────────────────────

    public function test_le_client_annule_sans_frais_une_demande_non_payee(): void
    {
        $mission = $this->mission(['status' => 'pending_artisan_acceptance', 'artisan_assigned_at' => now()]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v1/missions/{$mission->id}/cancellation-preview")
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.funded', false)
            ->assertJsonPath('data.penalty', 0);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/cancel", ['reason' => 'Travaux reportés'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.paymentStatus', 'cancelled');

        $mission->refresh();
        $this->assertNotNull($mission->cancelled_at);
        $this->assertSame($this->client->id, $mission->cancelled_by);
        $this->assertSame('Travaux reportés', $mission->cancellation_reason);
        $this->assertNull($mission->cancellation_penalty);
        $this->assertSame(0, MobileMoneyPayout::count());
        $this->assertTrue($this->notified($this->artisan, 'mission.annulee.artisan'));
        $this->assertSame(['pending_artisan_acceptance>cancelled'], $this->history($mission));
    }

    public function test_un_tiers_ne_peut_ni_annuler_ni_consulter_le_cout_de_l_annulation(): void
    {
        $mission = $this->mission();
        $tiers = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($tiers, 'sanctum')->getJson("/api/v1/missions/{$mission->id}/cancellation-preview")->assertForbidden();
        $this->actingAs($tiers, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/cancel")->assertForbidden();
        $this->actingAs($this->artisan, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/cancel")->assertForbidden();

        $this->assertSame('draft', (string) $mission->fresh()->status);
    }

    public function test_l_annulation_d_une_mission_financee_rembourse_le_sequestre_moins_la_penalite_de_7_pour_cent(): void
    {
        $mission = $this->fundedMission(100000);
        $wallets = app(WalletService::class);
        $escrow = $wallets->getMissionEscrowBalance($mission, WalletType::WALLET_MO)
            + $wallets->getMissionEscrowBalance($mission, WalletType::WALLET_MATERIAUX);
        $this->assertGreaterThan(0, $escrow);
        $penalty = (int) round($escrow * 0.07);
        $adminBefore = (int) $this->admin->fresh()->wallet_mo;

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v1/missions/{$mission->id}/cancellation-preview")
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.funded', true)
            ->assertJsonPath('data.escrow', $escrow)
            ->assertJsonPath('data.penalty', $penalty)
            ->assertJsonPath('data.refund', $escrow - $penalty);

        // Le montant envoyé par l'application n'entre dans aucun calcul.
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/cancel", ['reason' => 'Changement de projet', 'penalty' => 0, 'refund' => 999999999])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.paymentStatus', 'refunded')
            ->assertJsonPath('data.cancellationPenalty', $penalty)
            ->assertJsonPath('data.cancellationRefund', $escrow - $penalty);

        // Le client est remboursé par le circuit des versements.
        $payout = MobileMoneyPayout::sole();
        $this->assertSame($this->client->id, $payout->user_id);
        $this->assertSame($escrow - $penalty, $payout->montant);
        $this->assertSame(MobileMoneyPayout::STATUT_VERSE, $payout->statut);

        // La pénalité revient à la plateforme ; plus rien ne reste en séquestre.
        $this->assertSame($adminBefore + $penalty, (int) $this->admin->fresh()->wallet_mo);
        $this->assertSame(0, (int) $this->artisan->fresh()->wallet_mo);
        $this->assertSame(0, (int) $this->artisan->fresh()->wallet_materiaux);

        $mission->refresh();
        $this->assertSame(7.0, $mission->cancellation_penalty_rate);
        $this->assertContains('funded_locked>cancelled', $this->history($mission));
        $this->assertTrue($this->notified($this->client, 'mission.annulee_remboursee.client'));
        $this->assertTrue($this->notified($this->artisan, 'mission.annulee.artisan'));
    }

    public function test_une_seconde_annulation_ne_rembourse_pas_deux_fois(): void
    {
        $mission = $this->fundedMission();

        $this->actingAs($this->client, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/cancel")->assertOk();
        $this->actingAs($this->client, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/cancel")->assertOk();

        $this->assertSame(1, MobileMoneyPayout::count());
        $this->assertSame(1, MissionStateTransition::where('mission_id', $mission->id)->where('to_state', 'cancelled')->count());
    }

    public function test_le_taux_de_penalite_regle_au_backoffice_est_applique_et_fige_sur_la_mission(): void
    {
        Setting::updateOrCreate(['key' => 'mission_cancellation_penalty_rate'], ['value' => '10', 'type' => 'float', 'group' => 'missions']);
        $mission = $this->fundedMission(100000);
        $escrow = app(WalletService::class)->getMissionEscrowBalance($mission, WalletType::WALLET_MO);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.cancellationPenalty', (int) round($escrow * 0.10));

        Setting::where('key', 'mission_cancellation_penalty_rate')->update(['value' => '50']);
        $this->assertSame(10.0, $mission->fresh()->cancellation_penalty_rate);
    }

    public function test_un_remboursement_dont_le_virement_echoue_reste_du_au_client(): void
    {
        $this->mock(WaveService::class, function (MockInterface $mock) {
            $mock->shouldReceive('transferToMobileMoney')->andThrow(new \Exception('Numéro Wave inconnu'));
        });
        $mission = $this->fundedMission();

        $this->actingAs($this->client, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/cancel")->assertOk();

        $payout = MobileMoneyPayout::sole();
        $this->assertSame(MobileMoneyPayout::STATUT_ECHOUE, $payout->statut);
        $this->assertNotNull($payout->next_retry_at);
        // Les fonds à rembourser restent sur les portefeuilles tant que le virement n'a pas abouti.
        $this->assertSame($payout->montant, (int) $this->artisan->fresh()->wallet_mo + (int) $this->artisan->fresh()->wallet_materiaux);
        $this->assertSame('cancelled', (string) $mission->fresh()->status);
    }

    public function test_l_annulation_est_refusee_une_fois_le_chantier_commence(): void
    {
        $soumise = $this->fundedMission();
        $soumise->jalons()->orderBy('ordre')->first()->update(['statut' => 'soumis']);

        $materiel = $this->fundedMission();
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        JCode::create([
            'mission_id' => $materiel->id, 'artisan_id' => $this->artisan->id, 'fournisseur_id' => $fournisseur->id,
            'code' => 'PA-TEST', 'montant' => 10000, 'statut' => 'utilise', 'expires_at' => now()->addDay(),
        ]);

        $enCours = $this->mission(['status' => 'in_progress', 'montant_total' => 100000]);

        foreach ([[$soumise, 'étape'], [$materiel, 'matériel'], [$enCours, 'chantier a commencé']] as [$mission, $motif]) {
            $this->actingAs($this->client, 'sanctum')
                ->getJson("/api/v1/missions/{$mission->id}/cancellation-preview")
                ->assertOk()
                ->assertJsonPath('data.allowed', false);

            $response = $this->actingAs($this->client, 'sanctum')
                ->postJson("/api/v1/missions/{$mission->id}/cancel")
                ->assertStatus(422);

            $this->assertStringContainsString($motif, $response->json('message'));
            $this->assertStringContainsString('litige', $response->json('message'));
            $this->assertNotSame('cancelled', (string) $mission->fresh()->status);
        }

        $this->assertSame(0, MobileMoneyPayout::count());
    }

    public function test_les_bons_materiels_actifs_sont_expires_a_l_annulation(): void
    {
        $mission = $this->fundedMission();
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $jcode = JCode::create([
            'mission_id' => $mission->id, 'artisan_id' => $this->artisan->id, 'fournisseur_id' => $fournisseur->id,
            'code' => 'PA-ACTF', 'montant' => 10000, 'statut' => 'actif', 'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->client, 'sanctum')->postJson("/api/v1/missions/{$mission->id}/cancel")->assertOk();

        $this->assertSame('expire', $jcode->fresh()->statut);
    }

    public function test_le_backoffice_borne_le_taux_de_penalite(): void
    {
        $setting = Setting::updateOrCreate(['key' => 'mission_cancellation_penalty_rate'], ['value' => '7', 'type' => 'float', 'group' => 'missions']);

        $this->actingAs($this->admin)->put("/admin/settings/{$setting->id}", ['value' => '150'])->assertSessionHasErrors('value');
        $this->actingAs($this->admin)->put("/admin/settings/{$setting->id}", ['value' => '-1'])->assertSessionHasErrors('value');
        $this->assertSame('7', $setting->fresh()->value);

        $this->actingAs($this->admin)->put("/admin/settings/{$setting->id}", ['value' => '12.5'])->assertSessionHasNoErrors();
        $this->assertSame('12.5', $setting->fresh()->value);
    }

    // ── Lot 4 : délai de réponse de l'artisan ────────────────────────────

    public function test_une_demande_sans_reponse_depuis_24_heures_revient_en_recherche_d_artisan(): void
    {
        $echue = $this->mission(['status' => 'pending_artisan_acceptance', 'artisan_assigned_at' => now()->subHours(25)]);
        $recente = $this->mission(['status' => 'pending_artisan_acceptance', 'artisan_assigned_at' => now()->subHours(23)]);

        $this->artisan('missions:expire-artisan-requests')->assertSuccessful();

        $echue->refresh();
        $this->assertSame('draft', (string) $echue->status);
        $this->assertNull($echue->artisan_id);
        $this->assertTrue($this->notified($this->client, 'mission.sans_reponse.client'));
        $this->assertTrue($this->notified($this->artisan, 'mission.demande_retiree.artisan'));

        $line = MissionStateTransition::where('mission_id', $echue->id)->sole();
        $this->assertNull($line->user_id);
        $this->assertStringContainsString('24 heures', $line->reason);

        $this->assertSame('pending_artisan_acceptance', (string) $recente->fresh()->status);
        $this->assertSame($this->artisan->id, $recente->fresh()->artisan_id);
    }

    public function test_l_artisan_est_relance_une_seule_fois_a_mi_delai(): void
    {
        $mission = $this->mission(['status' => 'pending_artisan_acceptance', 'artisan_assigned_at' => now()->subHours(13)]);

        $this->artisan('missions:expire-artisan-requests')->assertSuccessful();
        $this->artisan('missions:expire-artisan-requests')->assertSuccessful();

        $this->assertSame(1, Notification::where('user_id', $this->artisan->id)->where('event_key', 'mission.relance_demande.artisan')->count());
        $this->assertSame('pending_artisan_acceptance', (string) $mission->fresh()->status);
    }

    public function test_assigner_un_autre_artisan_fait_repartir_le_delai_et_previent_l_artisan_remplace(): void
    {
        $mission = $this->mission(['status' => 'pending_artisan_acceptance', 'artisan_assigned_at' => now()->subHours(20), 'artisan_reminded_at' => now()->subHours(8)]);
        $autre = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/assign-artisan", ['artisan_id' => $autre->id])
            ->assertOk();

        $mission->refresh();
        $this->assertSame($autre->id, $mission->artisan_id);
        $this->assertTrue($mission->artisan_assigned_at->gt(now()->subMinute()));
        $this->assertNull($mission->artisan_reminded_at);
        $this->assertNotNull($response->json('data.artisanResponseDeadline'));
        $this->assertTrue($this->notified($this->artisan, 'mission.demande_retiree.artisan'));
        $this->assertTrue($this->notified($autre, 'mission.demande_devis.artisan'));
    }

    public function test_un_artisan_au_compte_suspendu_ne_peut_pas_etre_assigne(): void
    {
        $mission = $this->mission();
        $suspendu = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'account_status' => 'suspendu']);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v1/missions/{$mission->id}/assign-artisan", ['artisan_id' => $suspendu->id])
            ->assertStatus(422);

        $this->assertSame($this->artisan->id, $mission->fresh()->artisan_id);
    }

    // ── Lot 6 : contrat API ──────────────────────────────────────────────

    public function test_la_ressource_expose_le_libelle_francais_et_plus_aucune_valeur_factice(): void
    {
        $mission = $this->mission(['status' => 'pending_approval', 'completion_requested_at' => now()]);

        $data = $this->actingAs($this->client, 'sanctum')->getJson("/api/v1/missions/{$mission->id}")->assertOk()->json('data');

        $this->assertSame('pending_approval', $data['status']);
        $this->assertSame('En attente de validation client', $data['statusLabel']);
        $this->assertSame('pending_approval', $data['statusGemini']);
        $this->assertNotNull($data['finalApprovalDeadline']);
        $this->assertArrayNotHasKey('platformFeesBreakdown', $data['financials']);
        $this->assertArrayNotHasKey('tokenCode', $data['financials']);
        $this->assertNull($data['geminiEstimation']);
    }

    // ── Lot 7 : reconstitution de l'historique ───────────────────────────

    private function legacyCompletedMission(): Mission
    {
        // Mission clôturée avant le Chantier 19 : statut écrit directement, aucun historique.
        $mission = $this->mission(['status' => 'completed', 'montant_total' => 100000, 'montant_mo' => 100000]);

        $payment = Transaction::create([
            'mission_id' => $mission->id, 'user_id' => $this->client->id, 'type' => 'acompte', 'montant' => 100000,
            'wallet_source' => 'client_mobile_money', 'wallet_dest' => 'escrow_mission_'.$mission->id,
            'provider' => 'wave', 'statut' => 'confirme', 'reference_externe' => 'TXN-OLD-'.$mission->id,
        ]);
        Transaction::whereKey($payment->id)->update(['created_at' => '2026-08-01 09:00:00', 'updated_at' => '2026-08-01 09:05:00']);

        foreach ([1 => '2026-08-05 10:00:00', 2 => '2026-08-12 16:00:00'] as $ordre => $date) {
            Jalon::create([
                'mission_id' => $mission->id, 'ordre' => $ordre, 'description' => "Étape {$ordre}", 'montant' => 50000,
                'statut' => 'paye', 'valide_at' => $date, 'paye_at' => $date,
            ]);
        }

        return $mission;
    }

    public function test_l_historique_d_une_mission_cloturee_est_reconstitue_dans_l_ordre_et_aux_dates_reelles(): void
    {
        $mission = $this->legacyCompletedMission();
        $this->assertSame(0, MissionStateTransition::where('mission_id', $mission->id)->count());

        $this->artisan('missions:backfill-state-history')->assertSuccessful();
        $this->assertSame(0, MissionStateTransition::count(), 'Sans --fix, la commande ne fait que rapporter.');

        $this->artisan('missions:backfill-state-history', ['--fix' => true])->assertSuccessful();

        $lines = MissionStateTransition::where('mission_id', $mission->id)->orderBy('created_at')->orderBy('id')->get();

        $this->assertSame(
            ['draft>pending_funding', 'pending_funding>funded_locked', 'funded_locked>in_progress', 'in_progress>completed'],
            $lines->map(fn ($t) => "{$t->from_state}>{$t->to_state}")->all(),
        );
        $this->assertSame('2026-08-01 09:05:00', $lines[1]->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-05 10:00:00', $lines[2]->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-12 16:00:00', $lines[3]->created_at->format('Y-m-d H:i:s'));

        foreach ($lines as $line) {
            $this->assertNull($line->user_id);
            $this->assertTrue($line->metadata_json['reconstitue']);
            $this->assertArrayNotHasKey('date_inconnue', $line->metadata_json);
        }

        // L'API signale les lignes reconstituées.
        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v1/missions/{$mission->id}/state-history")
            ->assertOk()
            ->assertJsonPath('data.transitions.0.reconstituted', true);
    }

    public function test_la_reconstitution_est_idempotente(): void
    {
        $this->legacyCompletedMission();

        $this->artisan('missions:backfill-state-history', ['--fix' => true])->assertSuccessful();
        $count = MissionStateTransition::count();

        $this->artisan('missions:backfill-state-history', ['--fix' => true])->assertSuccessful();
        $this->assertSame($count, MissionStateTransition::count());
    }

    public function test_une_mission_a_l_historique_complet_n_est_pas_touchee(): void
    {
        $mission = $this->fundedMission();
        $before = $this->history($mission);

        $report = app(MissionHistoryBackfillService::class)->run(true);

        $this->assertSame(0, $report['lines']);
        $this->assertSame($before, $this->history($mission));
    }

    public function test_un_litige_passe_est_reconstitue_avec_son_issue(): void
    {
        $mission = $this->mission(['status' => 'cancelled', 'montant_total' => 100000]);
        $payment = Transaction::create([
            'mission_id' => $mission->id, 'user_id' => $this->client->id, 'type' => 'acompte', 'montant' => 100000,
            'wallet_source' => 'client_mobile_money', 'wallet_dest' => 'escrow_mission_'.$mission->id,
            'provider' => 'wave', 'statut' => 'confirme', 'reference_externe' => 'TXN-LIT-'.$mission->id,
        ]);
        Transaction::whereKey($payment->id)->update(['created_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00']);

        $litige = Litige::create([
            'mission_id' => $mission->id, 'declencheur_id' => $this->client->id, 'type' => 'client',
            'motif' => 'Abandon de chantier', 'description' => 'L\'artisan ne s\'est jamais présenté.',
            'statut' => 'resolu', 'decision' => 'client', 'resolu_at' => '2026-07-10 12:00:00',
        ]);
        Litige::whereKey($litige->id)->update(['created_at' => '2026-07-04 12:00:00']);

        app(MissionHistoryBackfillService::class)->run(true);

        $this->assertSame(
            ['draft>pending_funding', 'pending_funding>funded_locked', 'funded_locked>disputed', 'disputed>cancelled'],
            $this->history($mission),
        );
    }

    public function test_un_etat_sans_fait_date_est_reconstitue_avec_la_mention_date_inconnue(): void
    {
        // Mission « en cours » sans paiement ni étape enregistrés : rien ne date son parcours.
        $mission = $this->mission(['status' => 'in_progress', 'montant_total' => 100000]);

        app(MissionHistoryBackfillService::class)->run(true);

        $line = MissionStateTransition::where('mission_id', $mission->id)->sole();
        $this->assertSame('in_progress', $line->to_state);
        $this->assertTrue($line->metadata_json['reconstitue']);
        $this->assertTrue($line->metadata_json['date_inconnue']);
    }
}
