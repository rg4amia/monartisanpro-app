<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Permission;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, string> $capabilities */
    private function restrictedAdmin(array $capabilities): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        foreach (Permission::whereIn('name', $capabilities)->pluck('id') as $permissionId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
            ]);
        }

        return $admin;
    }

    private function trade(): Trade
    {
        $sector = Sector::create(['name' => 'BTP']);

        return Trade::create(['sector_id' => $sector->id, 'name' => 'Maçonnerie']);
    }

    /**
     * Paie et active le séquestre d'accès aux candidatures pour une offre,
     * préalable désormais obligatoire à toute consultation des postulants.
     */
    private function unlockApplicants(User $client, RecruitmentOffer $offer, int $dailyRate = 10000, ?int $totalDays = 5): void
    {
        $response = $this->actingAs($client)->postJson("/api/v1/recruitment-offers/{$offer->id}/unlock-applicants", [
            'daily_rate' => $dailyRate,
            'total_days' => $totalDays,
            'provider' => 'wave',
            'phone' => '+2250700000000',
        ]);

        $transactionId = $response->json('data.transaction_id');
        Transaction::findOrFail($transactionId)->update(['statut' => PaymentStatus::CONFIRME]);

        $this->actingAs($client)->postJson("/api/v1/recruitment-offers/{$offer->id}/activate-applicants-unlock", [
            'transaction_id' => $transactionId,
        ])->assertOk();
    }

    private function offerPayload(Trade $trade): array
    {
        return [
            'trade_id' => $trade->id,
            'title' => 'Renfort maçons — chantier Cocody',
            'description' => 'Besoin de 3 maçons pour 2 semaines.',
            'mission_type' => 'tacheron_brigade',
            'commune' => 'Cocody',
            'openings_count' => 3,
        ];
    }

    public function test_kyc_verified_client_offer_is_published_immediately(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $trade = $this->trade();

        $response = $this->actingAs($client)->postJson('/api/v1/recruitment-offers', $this->offerPayload($trade));

        $response->assertCreated();
        $this->assertSame('active', RecruitmentOffer::first()->status);
        $this->assertSame('client', RecruitmentOffer::first()->creator_type);
    }

    public function test_unverified_client_offer_goes_to_pending_review(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'en_attente']);
        $trade = $this->trade();

        $this->actingAs($client)->postJson('/api/v1/recruitment-offers', $this->offerPayload($trade))
            ->assertCreated();

        $this->assertSame('pending_review', RecruitmentOffer::first()->status);
    }

    public function test_admin_offer_is_published_immediately_regardless_of_kyc(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'en_attente']);
        $trade = $this->trade();

        $this->actingAs($admin)->postJson('/api/v1/recruitment-offers', $this->offerPayload($trade))
            ->assertCreated();

        $this->assertSame('active', RecruitmentOffer::first()->status);
    }

    public function test_offer_stores_date_debut_and_deadline(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $trade = $this->trade();

        $payload = $this->offerPayload($trade);
        $payload['date_debut'] = now()->addDays(2)->toDateString();
        $payload['deadline_at'] = now()->addDays(10)->toDateString();

        $this->actingAs($client)->postJson('/api/v1/recruitment-offers', $payload)
            ->assertCreated();

        $offer = RecruitmentOffer::first();
        $this->assertNotNull($offer->date_debut);
        $this->assertNotNull($offer->deadline_at);
    }

    public function test_offer_rejects_end_date_before_start_date(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $trade = $this->trade();

        $payload = $this->offerPayload($trade);
        $payload['date_debut'] = now()->addDays(10)->toDateString();
        $payload['deadline_at'] = now()->addDays(2)->toDateString();

        $this->actingAs($client)->postJson('/api/v1/recruitment-offers', $payload)
            ->assertStatus(422);
    }

    public function test_posting_can_be_disabled_per_space(): void
    {
        DB::table('recruitment_settings')->where('key', 'client_posting_enabled')->update(['value' => '0']);

        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif']);
        $trade = $this->trade();

        $this->actingAs($client)->postJson('/api/v1/recruitment-offers', $this->offerPayload($trade))
            ->assertStatus(422);

        $this->actingAs($fournisseur)->postJson('/api/v1/recruitment-offers', $this->offerPayload($trade))
            ->assertCreated();
    }

    public function test_admin_toggles_posting_per_space_from_backoffice(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $this->actingAs($admin)
            ->post('/admin/recruitment/settings', ['client_posting_enabled' => '0', 'fournisseur_posting_enabled' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('0', DB::table('recruitment_settings')->where('key', 'client_posting_enabled')->value('value'));
        $this->assertSame('1', DB::table('recruitment_settings')->where('key', 'fournisseur_posting_enabled')->value('value'));
    }

    public function test_public_index_only_lists_active_offers(): void
    {
        $trade = $this->trade();
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        RecruitmentOffer::create([
            'creator_id' => $admin->id,
            'creator_type' => 'admin',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        RecruitmentOffer::create([
            'creator_id' => $admin->id,
            'creator_type' => 'admin',
            'trade_id' => $trade->id,
            'title' => 'Offre en attente de modération',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/recruitment-offers');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_kyc_verified_artisan_can_apply_once(): void
    {
        $trade = $this->trade();
        $admin = User::factory()->create(['role' => 'admin']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_prosartisan' => 650]);

        $offer = RecruitmentOffer::create([
            'creator_id' => $admin->id,
            'creator_type' => 'admin',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply")
            ->assertCreated();

        $application = RecruitmentApplication::first();
        $this->assertNotNull($application->matching_score);
        $this->assertSame(65.0, (float) $application->matching_score);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply")
            ->assertStatus(422);
    }

    public function test_applying_and_status_changes_notify_the_right_users(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply");

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'recruitment',
            'title' => 'Nouvelle candidature',
        ]);

        $application = RecruitmentApplication::first();

        $this->actingAs($client)->patchJson(
            "/api/v1/recruitment-offers/{$offer->id}/applications/{$application->id}/status",
            ['status' => 'shortlisted'],
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'recruitment',
            'title' => 'Candidature mise à jour',
        ]);
    }

    public function test_offer_moderation_notifies_the_creator(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'en_attente']);
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre en attente',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin)->post("/admin/recruitment/{$offer->id}/approve");

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'recruitment',
            'title' => 'Offre publiée',
        ]);
    }

    public function test_offer_owner_can_view_applications_but_other_users_cannot(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $otherClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply")
            ->assertCreated();

        // Tant que le séquestre d'accès n'est pas payé, la liste reste verrouillée.
        $this->actingAs($client)->getJson("/api/v1/recruitment-offers/{$offer->id}/applications")
            ->assertStatus(402);

        $this->unlockApplicants($client, $offer);

        $response = $this->actingAs($client)->getJson("/api/v1/recruitment-offers/{$offer->id}/applications")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Le numéro de l'artisan n'est jamais transmis au recruteur.
        $response->assertJsonMissingPath('data.0.artisan.phone');

        $this->actingAs($otherClient)->getJson("/api/v1/recruitment-offers/{$offer->id}/applications")
            ->assertStatus(403);
    }

    public function test_admin_can_view_applications_without_paying_the_escrow(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply");

        $this->actingAs($admin)->getJson("/api/v1/recruitment-offers/{$offer->id}/applications")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_recruiter_can_request_a_callback_only_after_unlocking_applicants(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply");
        $application = RecruitmentApplication::first();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/request-callback")
            ->assertStatus(422);

        $this->unlockApplicants($client, $offer);

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/request-callback")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'recruitment',
            'title' => 'Demande de rappel',
        ]);
    }

    public function test_offer_owner_can_update_application_status(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $otherClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply");
        $application = RecruitmentApplication::first();

        $this->actingAs($otherClient)->patchJson(
            "/api/v1/recruitment-offers/{$offer->id}/applications/{$application->id}/status",
            ['status' => 'contacted'],
        )->assertStatus(422);

        $this->actingAs($client)->patchJson(
            "/api/v1/recruitment-offers/{$offer->id}/applications/{$application->id}/status",
            ['status' => 'contacted'],
        )->assertOk();

        $this->assertSame('contacted', $application->fresh()->status);
    }

    public function test_unverified_artisan_cannot_apply(): void
    {
        $trade = $this->trade();
        $admin = User::factory()->create(['role' => 'admin']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'en_attente']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $admin->id,
            'creator_type' => 'admin',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply")
            ->assertStatus(403);
    }

    public function test_admin_can_approve_pending_offer(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'en_attente']);
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre en attente',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin)->post("/admin/recruitment/{$offer->id}/approve")
            ->assertRedirect();

        $this->assertSame('active', $offer->fresh()->status);
    }

    public function test_admin_can_view_and_update_applications(): void
    {
        $trade = $this->trade();
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $admin->id,
            'creator_type' => 'admin',
            'trade_id' => $trade->id,
            'title' => 'Offre active',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Yopougon',
            'status' => 'active',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-offers/{$offer->id}/apply");
        $application = RecruitmentApplication::first();

        $this->actingAs($admin)->getJson("/admin/recruitment/{$offer->id}/applications")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)->post(
            "/admin/recruitment/{$offer->id}/applications/{$application->id}/status",
            ['status' => 'confirmed'],
        )->assertRedirect();

        $this->assertSame('confirmed', $application->fresh()->status);
    }

    public function test_recruitment_admin_page_requires_capability(): void
    {
        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->get('/admin/recruitment')
            ->assertForbidden();

        $this->actingAs($this->restrictedAdmin(['admin.recruitment.manage']))
            ->get('/admin/recruitment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/recruitment')->has('recruitmentStats'));
    }
}
