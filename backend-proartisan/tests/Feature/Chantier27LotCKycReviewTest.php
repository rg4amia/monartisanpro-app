<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\KycDocument;
use App\Models\Notification;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Chantier 27, lot C — revue KYC cohérente (constats H1 à H5 de l'analyse du
 * module « Utilisateurs »).
 */
class Chantier27LotCKycReviewTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Photo de la carte illisible, merci de la reprendre.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // Sans clé Gemini, l'analyse est indisponible : aucun dossier n'est auto-approuvé.
        config(['services.gemini.api_key' => null]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function pendingArtisan(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'artisan', 'kyc_status' => 'en_attente', ...$attributes]);
    }

    private function document(User $user, string $type, string $statut = 'en_attente'): KycDocument
    {
        return KycDocument::create([
            'user_id' => $user->id,
            'type' => $type,
            'file_url' => "kyc/{$type}-{$user->id}-".uniqid().'.jpg',
            'statut' => $statut,
        ]);
    }

    private function completeFile(User $user, string $statut = 'en_attente'): void
    {
        $this->document($user, 'cni', $statut);
        $this->document($user, 'selfie', $statut);
    }

    // ── H1. Approbation sans pièce ──────────────────────────────────────────

    public function test_a_file_without_both_documents_cannot_be_approved(): void
    {
        $admin = $this->admin();
        $empty = $this->pendingArtisan();
        $partial = $this->pendingArtisan();
        $this->document($partial, 'cni');

        foreach ([$empty, $partial] as $user) {
            $this->actingAs($admin)->post("/admin/kyc/{$user->id}/review", ['decision' => 'approuve'])
                ->assertSessionHas('error', 'Ce dossier ne contient pas les deux pièces.');

            $this->assertSame('en_attente', $user->refresh()->kyc_status);
        }

        $this->assertSame(0, AdminActivityLog::where('action', 'kyc.reviewed')->count());
    }

    public function test_the_mobile_admin_route_refuses_an_incomplete_file_with_422(): void
    {
        $user = $this->pendingArtisan();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/admin/kyc/{$user->id}/review", ['decision' => 'approuve'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ce dossier ne contient pas les deux pièces.');

        $this->assertSame('en_attente', $user->refresh()->kyc_status);
    }

    public function test_a_complete_file_is_approved_with_its_two_documents(): void
    {
        $admin = $this->admin();
        $user = $this->pendingArtisan();
        $this->completeFile($user);

        $this->actingAs($admin)->post("/admin/kyc/{$user->id}/review", ['decision' => 'approuve'])
            ->assertSessionHas('success');

        $this->assertSame('actif', $user->refresh()->kyc_status);
        $this->assertSame(2, KycDocument::where('user_id', $user->id)->where('statut', 'approuve')->where('reviewed_by', $admin->id)->count());
    }

    public function test_bulk_approval_skips_incomplete_files_and_reports_them(): void
    {
        $admin = $this->admin();
        $complete = $this->pendingArtisan();
        $this->completeFile($complete);
        $empty = $this->pendingArtisan();

        $this->actingAs($admin)->post('/admin/kyc/bulk-review', [
            'user_ids' => [$complete->id, $empty->id],
            'decision' => 'approuve',
        ])->assertSessionHas('success');

        $this->assertSame('actif', $complete->refresh()->kyc_status);
        $this->assertSame('en_attente', $empty->refresh()->kyc_status);

        $summary = AdminActivityLog::where('action', 'kyc.bulk_reviewed')->firstOrFail();
        $this->assertSame(1, $summary->context['processed']);
        $this->assertArrayHasKey($empty->id, $summary->context['skipped']);
    }

    // ── H2. État du compte ──────────────────────────────────────────────────

    public function test_a_deleted_account_cannot_be_reviewed(): void
    {
        $admin = $this->admin();
        $user = $this->pendingArtisan();
        $this->completeFile($user);
        $user->delete();

        $this->actingAs($admin)->post("/admin/kyc/{$user->id}/review", ['decision' => 'approuve'])
            ->assertNotFound();

        $this->assertSame('en_attente', User::withTrashed()->findOrFail($user->id)->kyc_status);
    }

    // ── H3. Pièces après un rejet ───────────────────────────────────────────

    public function test_approving_after_a_rejection_approves_the_documents_too(): void
    {
        $admin = $this->admin();
        $user = $this->pendingArtisan(['kyc_status' => 'rejete']);
        $this->completeFile($user, 'rejete');
        KycDocument::where('user_id', $user->id)->update(['rejection_reason' => self::REASON]);

        $this->actingAs($admin)->post("/admin/kyc/{$user->id}/review", ['decision' => 'approuve'])
            ->assertSessionHas('success');

        $this->assertSame('actif', $user->refresh()->kyc_status);
        $this->assertSame(0, KycDocument::where('user_id', $user->id)->where('statut', 'rejete')->count());
        $this->assertSame(0, KycDocument::where('user_id', $user->id)->whereNotNull('rejection_reason')->count());
    }

    // ── H4. Retour d'un utilisateur rejeté ──────────────────────────────────

    public function test_a_rejected_user_sending_a_new_document_returns_to_the_review_list(): void
    {
        $admin = $this->admin();
        $user = $this->pendingArtisan(['kyc_status' => 'rejete']);
        $this->completeFile($user, 'rejete');

        Sanctum::actingAs($user);
        $this->post('/api/v1/kyc/upload-cni', [
            'file' => UploadedFile::fake()->image('cni.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        // Une seule pièce renvoyée : le dossier revient à l'examen, sans
        // auto-approbation tant que l'autre pièce est celle du rejet.
        $this->assertSame('en_attente', $user->refresh()->kyc_status);
        $blockers = app(KycService::class)->autoApprovalBlockers($user);
        $this->assertContains('pieces_deja_examinees', $blockers);
        $this->assertContains('dossier_rejete_par_un_administrateur', $blockers);

        $this->actingAs($admin, 'web')->get('/admin/kyc')->assertInertia(fn ($page) => $page
            ->where('kycUsersPage.data.0.id', $user->id));
    }

    // ── H5. Motif du rejet ──────────────────────────────────────────────────

    public function test_the_rejection_reason_reaches_the_user(): void
    {
        $admin = $this->admin();
        $user = $this->pendingArtisan();
        $this->completeFile($user);

        $this->actingAs($admin)->post("/admin/kyc/{$user->id}/review", [
            'decision' => 'rejete',
            'rejection_reason' => self::REASON,
        ])->assertSessionHas('success');

        $notification = Notification::where('user_id', $user->id)->where('event_key', 'kyc.rejete.utilisateur')->firstOrFail();
        $this->assertStringContainsString(self::REASON, $notification->body);
        $this->assertStringNotContainsString('{motif}', $notification->body);

        Sanctum::actingAs($user->refresh());
        $this->getJson('/api/v1/kyc/status')
            ->assertOk()
            ->assertJsonPath('data.kyc_status', 'rejete')
            ->assertJsonPath('data.rejection_reason', self::REASON);
    }

    public function test_the_rejection_reason_is_kept_for_a_file_without_documents(): void
    {
        $admin = $this->admin();
        $user = $this->pendingArtisan();

        $this->actingAs($admin)->post("/admin/kyc/{$user->id}/review", [
            'decision' => 'rejete',
            'rejection_reason' => self::REASON,
        ])->assertSessionHas('success');

        Sanctum::actingAs($user->refresh());
        $this->getJson('/api/v1/kyc/status')->assertJsonPath('data.rejection_reason', self::REASON);
    }

    public function test_no_rejection_reason_is_returned_once_the_file_is_no_longer_rejected(): void
    {
        $user = $this->pendingArtisan();
        $this->completeFile($user);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/kyc/status')->assertJsonPath('data.rejection_reason', null);
    }
}
