<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecruitmentEngagementTest extends TestCase
{
    use RefreshDatabase;

    private function trade(): Trade
    {
        $sector = Sector::create(['name' => 'BTP']);

        return Trade::create(['sector_id' => $sector->id, 'name' => 'Maçonnerie']);
    }

    /** @return array{offer: RecruitmentOffer, application: RecruitmentApplication, client: User, artisan: User, admin: User} */
    private function setupConfirmableApplication(): array
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif', 'score_prosartisan' => 500]);
        User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
        $admin = User::where('role', 'admin')->first();

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
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

        $application = RecruitmentApplication::create([
            'offer_id' => $offer->id,
            'artisan_id' => $artisan->id,
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        return compact('offer', 'application', 'client', 'artisan', 'admin');
    }

    public function test_recruiter_can_create_engagement_from_offer_dates_and_artisan_can_accept(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        $response = $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", [
            'daily_rate' => 10000,
        ]);

        $response->assertCreated();

        $engagement = RecruitmentEngagement::first();
        $this->assertSame(3, $engagement->total_days);
        $this->assertSame(30000, $engagement->montant_total);
        $this->assertSame('pending_artisan_acceptance', $engagement->status);
        $this->assertSame('confirmed', $application->fresh()->status);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept")
            ->assertOk();

        $engagement->refresh();
        $this->assertSame('pending_payment', $engagement->status);
        $this->assertCount(3, $engagement->workdays);
        $this->assertTrue($engagement->workdays->every(fn ($w) => $w->status === 'awaiting_payment'));
    }

    public function test_engagement_requires_total_days_when_offer_has_no_dates(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

        $offer = RecruitmentOffer::create([
            'creator_id' => $client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Offre sans dates',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Cocody',
            'status' => 'active',
        ]);

        $application = RecruitmentApplication::create([
            'offer_id' => $offer->id,
            'artisan_id' => $artisan->id,
            'status' => 'submitted',
            'applied_at' => now(),
        ]);

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", [
            'daily_rate' => 10000,
        ])->assertStatus(422);

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", [
            'daily_rate' => 10000,
            'total_days' => 5,
        ])->assertCreated();
    }

    public function test_full_escrow_flow_credits_artisan_net_and_platform_commission(): void
    {
        DB::table('settings')->where('key', 'commission_recruitment')->update(['value' => '0.10']);

        ['application' => $application, 'client' => $client, 'artisan' => $artisan, 'admin' => $admin] = $this->setupConfirmableApplication();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'recruitment',
            'title' => 'Proposition de mission',
        ]);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept");

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'recruitment',
            'title' => 'Engagement accepté',
        ]);

        $payResponse = $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/pay", [
            'provider' => 'wave',
            'phone' => '+2250700000000',
        ]);
        $payResponse->assertOk();
        $transactionId = $payResponse->json('data.transaction_id');

        $transaction = Transaction::findOrFail($transactionId);
        $this->assertSame(30000, $transaction->montant);
        $transaction->update(['statut' => PaymentStatus::CONFIRME]);

        $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/activate", [
            'transaction_id' => $transactionId,
        ])->assertOk();

        $engagement->refresh();
        $this->assertSame('active', $engagement->status);
        $this->assertTrue($engagement->workdays->every(fn ($w) => $w->status === 'pending'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'payment',
            'title' => 'Séquestre payé',
        ]);

        $artisanBalanceBefore = $artisan->fresh()->wallet_mo;
        $adminBalanceBefore = $admin->fresh()->wallet_mo;

        $firstWorkday = $engagement->workdays->first();
        $this->actingAs($client)->postJson(
            "/api/v1/recruitment-engagements/{$engagement->id}/workdays/{$firstWorkday->id}/validate",
        )->assertOk();

        $this->assertSame($artisanBalanceBefore + 9000, $artisan->fresh()->wallet_mo);
        $this->assertSame($adminBalanceBefore + 1000, $admin->fresh()->wallet_mo);
        $this->assertSame('validated', $firstWorkday->fresh()->status);
        $this->assertSame('active', $engagement->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'payment',
            'title' => 'Journée payée',
        ]);

        // Valide les 2 jours restants → l'engagement se termine et l'offre passe "pourvue".
        foreach ($engagement->workdays()->where('id', '!=', $firstWorkday->id)->get() as $workday) {
            $this->actingAs($client)->postJson(
                "/api/v1/recruitment-engagements/{$engagement->id}/workdays/{$workday->id}/validate",
            )->assertOk();
        }

        $this->assertSame('completed', $engagement->fresh()->status);
        $this->assertSame('filled', $engagement->offer->fresh()->status);
    }

    public function test_cannot_validate_workday_before_escrow_activation(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();
        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept");

        $workday = $engagement->workdays()->first();

        $this->actingAs($client)->postJson(
            "/api/v1/recruitment-engagements/{$engagement->id}/workdays/{$workday->id}/validate",
        )->assertStatus(422);
    }

    public function test_engagement_can_be_extended_and_requires_new_escrow_payment(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();
        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept");

        $payResponse = $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/pay", ['provider' => 'wave', 'phone' => '+2250700000000']);
        $transactionId = $payResponse->json('data.transaction_id');
        Transaction::findOrFail($transactionId)->update(['statut' => PaymentStatus::CONFIRME]);
        $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/activate", ['transaction_id' => $transactionId]);

        $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/extend", [
            'additional_days' => 2,
        ])->assertOk();

        $engagement->refresh();
        $this->assertSame(5, $engagement->total_days);
        $this->assertSame(50000, $engagement->montant_total);
        $this->assertCount(2, $engagement->workdays()->where('status', 'awaiting_payment')->get());

        // Un jour de la prolongation ne peut pas être validé tant qu'il n'est pas payé.
        $extensionWorkday = $engagement->workdays()->where('day_number', 4)->first();
        $this->actingAs($client)->postJson(
            "/api/v1/recruitment-engagements/{$engagement->id}/workdays/{$extensionWorkday->id}/validate",
        )->assertStatus(422);

        // Complète le séquestre pour les 2 jours ajoutés.
        $topupResponse = $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/pay", ['provider' => 'wave', 'phone' => '+2250700000000']);
        $topupResponse->assertOk();
        $this->assertSame(20000, Transaction::findOrFail($topupResponse->json('data.transaction_id'))->montant);

        $topupTransactionId = $topupResponse->json('data.transaction_id');
        Transaction::findOrFail($topupTransactionId)->update(['statut' => PaymentStatus::CONFIRME]);
        $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/activate", ['transaction_id' => $topupTransactionId])
            ->assertOk();

        $this->assertSame('pending', $extensionWorkday->fresh()->status);
    }

    public function test_only_recruiter_owning_engagement_can_validate_workdays(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();
        $otherClient = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();
        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept");

        $workday = $engagement->workdays()->first();

        $this->actingAs($otherClient)->postJson(
            "/api/v1/recruitment-engagements/{$engagement->id}/workdays/{$workday->id}/validate",
        )->assertStatus(422);
    }

    public function test_artisan_can_decline_engagement(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/decline")
            ->assertOk();

        $this->assertSame('cancelled', $engagement->fresh()->status);
    }
}
