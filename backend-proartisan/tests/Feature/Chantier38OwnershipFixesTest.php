<?php

namespace Tests\Feature;

use App\Models\EvidenceVault;
use App\Models\JuryReview;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Permission;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chantier 38 — constats 5, 6, 7 et 9 de l'audit du 06/10/2026 : des données
 * d'un tiers remises à tout compte du bon rôle, sans lien vérifié avec la
 * ressource (Règle d'or 36), et des PDF nominatifs écrits sur le disque public.
 */
class Chantier38OwnershipFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif'] + $attributes);
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function restrictedAdmin(array $capabilities): User
    {
        $admin = $this->user('admin');

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    private function mission(User $client, User $artisan): Mission
    {
        return Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réfection de la salle de bain',
            'status' => 'in_progress',
            'montant_total' => 300000,
            'montant_materiaux' => 195000,
            'montant_mo' => 105000,
            'ratio_materiaux' => 0.65,
            'referent_required' => false,
        ]);
    }

    // ── Constat 6 : rapport de solvabilité ──────────────────────────────────

    public function test_le_rapport_de_solvabilite_d_un_artisan_n_est_pas_remis_a_un_autre_compte(): void
    {
        $artisan = $this->user('artisan');

        foreach (['client', 'artisan', 'fournisseur', 'livreur'] as $role) {
            $this->actingAs($this->user($role))
                ->getJson("/api/v1/artisans/{$artisan->id}/report")
                ->assertStatus(403);
        }
    }

    public function test_l_artisan_telecharge_son_propre_rapport(): void
    {
        $artisan = $this->user('artisan');

        $response = $this->actingAs($artisan)->get("/api/v1/artisans/{$artisan->id}/report");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_un_administrateur_ne_lit_le_rapport_qu_avec_la_capacite_de_consulter_les_utilisateurs(): void
    {
        $artisan = $this->user('artisan');

        $this->actingAs($this->restrictedAdmin(['admin.faq.manage']))
            ->getJson("/api/v1/artisans/{$artisan->id}/report")
            ->assertStatus(403);

        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->get("/api/v1/artisans/{$artisan->id}/report")
            ->assertOk();
    }

    public function test_aucun_pdf_n_est_ecrit_sur_le_disque_public(): void
    {
        $artisan = $this->user('artisan');

        // L'ancien code écrivait par `storage_path('app/public/…')`, hors du
        // disque simulé : le dossier réel est contrôlé lui aussi.
        $realPublicCopies = fn (): array => glob(storage_path("app/public/reports/solvability_report_{$artisan->id}_*.pdf")) ?: [];
        $before = $realPublicCopies();

        $this->actingAs($artisan)->get("/api/v1/artisans/{$artisan->id}/report")->assertOk();
        $this->actingAs($artisan)->get('/api/v1/micro-credit/report')->assertOk();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($before, $realPublicCopies());
    }

    public function test_les_pdf_restes_sur_le_disque_public_sont_retires(): void
    {
        $disk = Storage::disk('public');
        $disk->put('reports/solvability_report_23_20260928181003.pdf', 'pdf');
        $disk->put('receipts/recu_paiement_31_20260918214109.pdf', 'pdf');
        $disk->put('invoices/disbursement_invoice_4_20260901101010.pdf', 'pdf');
        $disk->put('cashouts/cashout_receipt_2_20260901101010.pdf', 'pdf');
        $disk->put('vitrine/plaquette.pdf', 'pdf');
        $disk->put('receipts/photo.jpg', 'jpg');

        $pdf = app(PdfService::class);

        $this->assertTrue($pdf->isPublicPath($disk->path('reports/solvability_report_23_20260928181003.pdf')));
        $this->assertFalse($pdf->isPublicPath(Storage::disk('local')->path('documents/reports/x.pdf')));

        $this->assertSame(4, $pdf->purgePublicCopies());
        $this->assertEqualsCanonicalizing(
            ['vitrine/plaquette.pdf', 'receipts/photo.jpg'],
            $disk->allFiles(),
        );
    }

    // ── Constat 5 : coffre de preuves ───────────────────────────────────────

    /**
     * @return array{0: EvidenceVault, 1: User, 2: User, 3: Litige}
     */
    private function sealedEvidence(): array
    {
        $client = $this->user('client', ['phone' => '+2250700000001']);
        $artisan = $this->user('artisan', ['phone' => '+2250700000002']);
        $mission = $this->mission($client, $artisan);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Fissures anormales constatées après ragréage.',
            'statut' => 'ouvert',
            'workflow_step' => 'instruction',
        ]);

        $vault = EvidenceVault::create([
            'litige_id' => $litige->id,
            'mission_id' => $mission->id,
            'evidence_type' => 'litige',
            'uploaded_by' => $client->id,
            'file_url' => 'vault/constat.jpg',
            'file_path' => 'vault/constat.jpg',
            'sha256_hash' => hash('sha256', 'constat'),
            'gps_lat' => 5.3456,
            'gps_lng' => -4.0123,
            'is_tampered' => false,
            'uploaded_at' => now(),
        ]);

        return [$vault, $client, $artisan, $litige];
    }

    public function test_une_preuve_n_est_lisible_par_aucun_compte_etranger_a_la_mission(): void
    {
        [$vault] = $this->sealedEvidence();

        foreach (['client', 'artisan', 'fournisseur', 'livreur', 'referent'] as $role) {
            $stranger = $this->user($role);

            $this->actingAs($stranger)
                ->getJson("/api/v1/evidence-vault/{$vault->id}/certificate")
                ->assertStatus(404)
                ->assertJsonMissing(['phone' => '+2250700000001']);

            $this->actingAs($stranger)
                ->getJson("/api/v1/evidence-vault/{$vault->id}/verify")
                ->assertStatus(404);
        }
    }

    public function test_le_refus_d_une_preuve_ne_se_distingue_pas_d_une_preuve_inexistante(): void
    {
        [$vault] = $this->sealedEvidence();
        $stranger = $this->user('client');

        $refused = $this->actingAs($stranger)->getJson("/api/v1/evidence-vault/{$vault->id}/certificate");
        $missing = $this->actingAs($stranger)->getJson('/api/v1/evidence-vault/'.($vault->id + 1000).'/certificate');

        $this->assertSame($missing->status(), $refused->status());
        $this->assertSame($missing->json(), $refused->json());
    }

    public function test_les_parties_lisent_le_certificat_sans_telephone(): void
    {
        [$vault, $client, $artisan] = $this->sealedEvidence();

        foreach ([$client, $artisan] as $party) {
            $response = $this->actingAs($party)
                ->getJson("/api/v1/evidence-vault/{$vault->id}/certificate")
                ->assertOk();

            $this->assertSame($client->name, $response->json('data.uploader.name'));
            $this->assertArrayNotHasKey('phone', $response->json('data.uploader'));
            $this->assertNotNull($response->json('data.gps'));
        }
    }

    public function test_un_jure_lit_le_certificat_sans_identite_ni_position(): void
    {
        [$vault, , , $litige] = $this->sealedEvidence();
        $juror = $this->user('artisan');

        JuryReview::create([
            'litige_id' => $litige->id,
            'jure_id' => $juror->id,
            'compensation' => 5000,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($juror)
            ->getJson("/api/v1/evidence-vault/{$vault->id}/certificate")
            ->assertOk();

        $this->assertNull($response->json('data.uploader'));
        $this->assertNull($response->json('data.gps'));
        $this->assertSame($vault->sha256_hash, $response->json('data.sha256_hash'));
    }

    public function test_un_administrateur_ne_lit_une_preuve_qu_avec_la_capacite_des_litiges(): void
    {
        [$vault] = $this->sealedEvidence();

        $this->actingAs($this->restrictedAdmin(['admin.faq.manage']))
            ->getJson("/api/v1/evidence-vault/{$vault->id}/certificate")
            ->assertStatus(404);

        $this->actingAs($this->restrictedAdmin(['admin.litiges.view']))
            ->getJson("/api/v1/evidence-vault/{$vault->id}/certificate")
            ->assertOk();
    }

    // ── Constat 7 : recrutement ─────────────────────────────────────────────

    /**
     * @return array{0: RecruitmentOffer, 1: RecruitmentApplication, 2: User, 3: User}
     */
    private function offerWithApplication(bool $unlocked): array
    {
        $sector = Sector::create(['name' => 'BTP']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Maçonnerie']);
        $recruiter = $this->user('client', ['phone' => '+2250700000011']);
        $artisan = $this->user('artisan', ['phone' => '+2250700000012']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $recruiter->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Renfort maçons',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Cocody',
            'date_debut' => now()->addDay()->toDateString(),
            'deadline_at' => now()->addDays(3)->toDateString(),
            'status' => 'active',
        ]);

        if ($unlocked) {
            $offer->forceFill(['applicants_unlocked_at' => now()])->save();
        }

        $application = RecruitmentApplication::create([
            'offer_id' => $offer->id,
            'artisan_id' => $artisan->id,
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        return [$offer, $application, $recruiter, $artisan];
    }

    public function test_aucun_engagement_sans_acces_paye_aux_candidatures(): void
    {
        [, $application, $recruiter] = $this->offerWithApplication(unlocked: false);

        $this->actingAs($recruiter)
            ->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000])
            ->assertStatus(422);

        $this->assertSame(0, RecruitmentEngagement::count());
    }

    public function test_le_telephone_de_l_artisan_n_est_pas_remis_avant_un_engagement_accepte_et_finance(): void
    {
        [, $application, $recruiter, $artisan] = $this->offerWithApplication(unlocked: true);

        $this->actingAs($recruiter)
            ->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000])
            ->assertCreated();

        $engagement = RecruitmentEngagement::firstOrFail();

        foreach (["/api/v1/recruitment-engagements/{$engagement->id}", '/api/v1/recruitment-engagements/mine'] as $url) {
            $body = $this->actingAs($recruiter)->getJson($url)->assertOk()->getContent();

            $this->assertStringNotContainsString($artisan->phone, $body);
            $this->assertStringContainsString($recruiter->phone, $body);
        }

        // L'artisan ne reçoit pas davantage le numéro du recruteur.
        $body = $this->actingAs($artisan)->getJson("/api/v1/recruitment-engagements/{$engagement->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString($recruiter->phone, $body);
        $this->assertStringContainsString($artisan->phone, $body);
    }

    public function test_le_telephone_est_remis_une_fois_l_engagement_actif(): void
    {
        [, $application, $recruiter, $artisan] = $this->offerWithApplication(unlocked: true);

        $this->actingAs($recruiter)
            ->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000])
            ->assertCreated();

        $engagement = RecruitmentEngagement::firstOrFail();
        $engagement->update(['status' => 'active']);

        $this->actingAs($recruiter)
            ->getJson("/api/v1/recruitment-engagements/{$engagement->id}")
            ->assertOk()
            ->assertJsonPath('data.artisan.phone', $artisan->phone);
    }

    // ── Constat 9 : suggestion de devis ─────────────────────────────────────

    public function test_un_artisan_n_obtient_pas_de_suggestion_sur_la_mission_d_un_autre(): void
    {
        $mission = $this->mission($this->user('client'), $this->user('artisan'));

        $this->actingAs($this->user('artisan'))
            ->getJson("/api/v1/missions/{$mission->id}/devis/suggest")
            ->assertStatus(403);
    }

    public function test_la_suggestion_de_devis_respecte_le_quota_ia(): void
    {
        $artisan = $this->user('artisan');
        $mission = $this->mission($this->user('client'), $artisan);

        $this->actingAs($artisan)
            ->getJson("/api/v1/missions/{$mission->id}/devis/suggest")
            ->assertOk();

        DB::table('ai_user_quotas')->insert([
            'user_id' => $artisan->id,
            'blocked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($artisan)
            ->getJson("/api/v1/missions/{$mission->id}/devis/suggest")
            ->assertStatus(429);
    }
}
