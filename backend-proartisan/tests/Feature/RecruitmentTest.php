<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Trade;
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
