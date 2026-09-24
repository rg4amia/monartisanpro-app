<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Notification;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\RecruitmentEngagementService;
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

    /**
     * Paie et active le séquestre d'accès aux candidatures d'une offre,
     * préalable à la consultation des postulants et réutilisable comme
     * acompte sur le premier engagement créé depuis cette offre.
     */
    private function unlockApplicants(User $client, RecruitmentOffer $offer, int $dailyRate, ?int $totalDays = null): void
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

    /**
     * Règle d'or 36 (plafond cumulatif) : un paiement ne débloque que les
     * journées qu'il couvre. Une prolongation ajoutée entre l'initiation et
     * l'activation restait sinon validable — donc payable à l'artisan — sans
     * avoir été financée.
     */
    public function test_escrow_payment_only_activates_the_workdays_it_paid_for(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();
        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept");

        $transactionId = $this->actingAs($client)
            ->postJson("/api/v1/recruitment-engagements/{$engagement->id}/pay", ['provider' => 'wave', 'phone' => '+2250700000000'])
            ->json('data.transaction_id');
        $this->assertSame(30000, Transaction::findOrFail($transactionId)->montant);

        // Prolongation de 2 jours AVANT que le paiement des 3 premiers soit confirmé.
        $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/extend", ['additional_days' => 2])->assertOk();

        Transaction::findOrFail($transactionId)->update(['statut' => PaymentStatus::CONFIRME]);
        $this->actingAs($client)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/activate", ['transaction_id' => $transactionId])->assertOk();

        $this->assertSame(3, $engagement->workdays()->where('status', 'pending')->count());
        $this->assertSame(2, $engagement->workdays()->where('status', 'awaiting_payment')->count());
    }

    /**
     * Règle d'or 36 : toute voie de confirmation d'un paiement (webhook,
     * interrogation de statut, simulateur) produit le même effet, sans
     * attendre que l'application appelle l'activation.
     */
    public function test_confirmed_payment_activates_engagement_escrow_without_app_call(): void
    {
        ['application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", ['daily_rate' => 10000]);
        $engagement = RecruitmentEngagement::first();
        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept");

        $transactionId = $this->actingAs($client)
            ->postJson("/api/v1/recruitment-engagements/{$engagement->id}/pay", ['provider' => 'wave', 'phone' => '+2250700000000'])
            ->json('data.transaction_id');

        app(PaymentService::class)->confirmSimulatedPayment(Transaction::findOrFail($transactionId));

        $engagement->refresh();
        $this->assertSame('active', $engagement->status);
        $this->assertTrue($engagement->workdays->every(fn ($w) => $w->status === 'pending'));

        // L'activation demandée ensuite par l'application reste un succès (idempotence).
        $this->actingAs($client)
            ->postJson("/api/v1/recruitment-engagements/{$engagement->id}/activate", ['transaction_id' => $transactionId])
            ->assertOk();
        $this->assertSame(1, Notification::where('user_id', $artisan->id)->where('title', 'Séquestre payé')->count());
    }

    public function test_confirmed_payment_unlocks_offer_applicants_without_app_call(): void
    {
        ['offer' => $offer, 'client' => $client] = $this->setupConfirmableApplication();

        $transactionId = $this->actingAs($client)->postJson("/api/v1/recruitment-offers/{$offer->id}/unlock-applicants", [
            'daily_rate' => 10000,
            'provider' => 'wave',
            'phone' => '+2250700000000',
        ])->json('data.transaction_id');

        app(PaymentService::class)->confirmSimulatedPayment(Transaction::findOrFail($transactionId));

        $this->assertTrue($offer->fresh()->applicantsUnlocked());

        $this->actingAs($client)
            ->postJson("/api/v1/recruitment-offers/{$offer->id}/activate-applicants-unlock", ['transaction_id' => $transactionId])
            ->assertOk();
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

    public function test_engagement_reuses_prepaid_offer_escrow_fully_and_credits_surplus(): void
    {
        ['offer' => $offer, 'application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        // Le client sur-estime le taux journalier (12000) pour débloquer les
        // candidatures ; l'engagement réel négocié à 10000/jour est donc
        // intégralement couvert par ce séquestre, avec un trop-perçu.
        $this->unlockApplicants($client, $offer, 12000);

        $clientBalanceBefore = $client->fresh()->wallet_mo;

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", [
            'daily_rate' => 10000,
        ])->assertCreated();

        $engagement = RecruitmentEngagement::first();
        $this->assertSame(36000, $engagement->prepaid_amount);
        $this->assertTrue($offer->fresh()->applicants_escrow_reserved);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept")
            ->assertOk();

        $engagement->refresh();
        $this->assertSame('active', $engagement->status);
        $this->assertTrue($engagement->workdays->every(fn ($w) => $w->status === 'pending'));

        // Trop-perçu (36000 payé - 30000 dû) crédité sur le portefeuille du client.
        $this->assertSame($clientBalanceBefore + 6000, $client->fresh()->wallet_mo);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => 'payment',
            'title' => 'Séquestre payé',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'payment',
            'title' => 'Trop-perçu remboursé',
        ]);
    }

    public function test_engagement_partially_covers_from_prepaid_offer_escrow_and_requires_topup(): void
    {
        ['offer' => $offer, 'application' => $application, 'client' => $client, 'artisan' => $artisan] = $this->setupConfirmableApplication();

        // Séquestre d'accès payé pour un seul jour à 10000 FCFA, mais
        // l'engagement réel porte sur 3 jours au même taux.
        $this->unlockApplicants($client, $offer, 10000, 1);

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$application->id}/engage", [
            'daily_rate' => 10000,
        ])->assertCreated();

        $engagement = RecruitmentEngagement::first();
        $this->assertSame(10000, $engagement->prepaid_amount);

        $this->actingAs($artisan)->postJson("/api/v1/recruitment-engagements/{$engagement->id}/accept")
            ->assertOk();

        $engagement->refresh();
        $this->assertSame('pending_payment', $engagement->status);
        $this->assertCount(1, $engagement->workdays()->where('status', 'pending')->get());
        $this->assertCount(2, $engagement->workdays()->where('status', 'awaiting_payment')->get());
        $this->assertSame(20000, $this->app->make(RecruitmentEngagementService::class)->unpaidAmount($engagement));
    }

    public function test_declining_engagement_releases_prepaid_offer_escrow_for_reuse(): void
    {
        $trade = $this->trade();
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $firstArtisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $secondArtisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

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

        $firstApplication = RecruitmentApplication::create([
            'offer_id' => $offer->id, 'artisan_id' => $firstArtisan->id, 'status' => 'submitted', 'applied_at' => now(),
        ]);
        $secondApplication = RecruitmentApplication::create([
            'offer_id' => $offer->id, 'artisan_id' => $secondArtisan->id, 'status' => 'submitted', 'applied_at' => now(),
        ]);

        $this->unlockApplicants($client, $offer, 10000);

        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$firstApplication->id}/engage", ['daily_rate' => 10000]);
        $firstEngagement = RecruitmentEngagement::where('application_id', $firstApplication->id)->firstOrFail();
        $this->assertTrue($offer->fresh()->applicants_escrow_reserved);

        $this->actingAs($firstArtisan)->postJson("/api/v1/recruitment-engagements/{$firstEngagement->id}/decline")
            ->assertOk();

        $this->assertFalse($offer->fresh()->applicants_escrow_reserved);

        // Le même séquestre pré-payé peut être réutilisé pour un second candidat.
        $this->actingAs($client)->postJson("/api/v1/recruitment-applications/{$secondApplication->id}/engage", ['daily_rate' => 10000]);
        $secondEngagement = RecruitmentEngagement::where('application_id', $secondApplication->id)->firstOrFail();

        $this->assertSame(30000, $secondEngagement->prepaid_amount);
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
