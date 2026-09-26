<?php

namespace Tests\Feature;

use App\Models\JuryReview;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\Trade;
use App\Models\User;
use App\Services\LitigeService;
use App\States\Mission\DisputedState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Scores;
use Tests\TestCase;

/**
 * Chantier 12 — seuls siègent des jurés éligibles : KYC actif, même métier
 * que l'artisan, étrangers aux parties, score recalculé depuis le ledger au
 * moins égal au seuil réglable (`settings.jury_min_score`, 800 par défaut).
 * Faute de trois jurés, aucun jury n'est convoqué et l'admin est alerté.
 */
class JurySelectionRulesTest extends TestCase
{
    use RefreshDatabase;

    private Trade $trade;

    private Litige $litige;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::create(['name' => 'Second œuvre']);
        $this->trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Carreleur']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $artisan = $this->makeArtisan(0);

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Carrelage terrasse',
            'status' => DisputedState::class,
            'montant_total' => 150000,
            'montant_mo' => 100000,
            'montant_materiaux' => 50000,
            'ratio_materiaux' => 0.3333,
        ]);

        $this->litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Carreaux fissurés.',
            'statut' => 'ouvert',
            'workflow_step' => 'preuves',
        ]);
    }

    /** Artisan du métier du litige, score adossé au ledger si `$ledger`. */
    private function makeArtisan(int $score, bool $ledger = true, ?Trade $trade = null): User
    {
        $user = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'score_prosartisan' => $score,
        ]);
        $user->artisanProfile()->create([
            'trade_id' => ($trade ?? $this->trade)->id,
            'experience_years' => 5,
        ]);
        if ($ledger && $score > 0) {
            Scores::backWithLedger($user);
        }

        return $user;
    }

    private function adminAlerts(): int
    {
        return Notification::where('user_id', $this->admin->id)->where('type', 'litige')->count();
    }

    public function test_un_score_stocke_sans_historique_ne_suffit_pas_a_sieger(): void
    {
        $this->makeArtisan(900);
        $this->makeArtisan(900);
        $this->makeArtisan(900, ledger: false);

        try {
            app(LitigeService::class)->assignJury($this->litige);
            $this->fail('Le jury ne devait pas être convoqué.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('2 juré(s) éligible(s) sur 3', $e->getMessage());
        }

        $this->assertSame(0, JuryReview::where('litige_id', $this->litige->id)->count());
        $this->assertSame('jury_indisponible', $this->litige->fresh()->jury_status);
        $this->assertSame(1, $this->adminAlerts());
    }

    public function test_seuls_les_jures_du_meme_metier_et_au_dessus_du_seuil_sont_convoques(): void
    {
        $other = Trade::create(['sector_id' => $this->trade->sector_id, 'name' => 'Plombier']);
        $eligible = [$this->makeArtisan(850), $this->makeArtisan(820), $this->makeArtisan(900)];
        $this->makeArtisan(950, trade: $other);
        $this->makeArtisan(700);

        app(LitigeService::class)->assignJury($this->litige);

        $convened = JuryReview::where('litige_id', $this->litige->id)->pluck('jure_id')->sort()->values()->all();
        $this->assertSame(collect($eligible)->pluck('id')->sort()->values()->all(), $convened);
        $this->assertSame(0, $this->adminAlerts());
    }

    public function test_l_admin_abaisse_le_seuil_pour_elargir_le_vivier(): void
    {
        $this->makeArtisan(650);
        $this->makeArtisan(620);
        $this->makeArtisan(610);

        $this->expectException(\DomainException::class);
        try {
            app(LitigeService::class)->assignJury($this->litige);
        } finally {
            Setting::updateOrCreate(
                ['key' => 'jury_min_score'],
                ['value' => '600', 'type' => 'integer', 'group' => 'litiges', 'label' => 'Score minimal juré']
            );
            app(LitigeService::class)->assignJury($this->litige);

            $this->assertSame(3, JuryReview::where('litige_id', $this->litige->id)->count());
            $this->assertSame('pending_jury', $this->litige->fresh()->jury_status);
        }
    }

    public function test_un_jure_non_remplacable_alerte_l_admin(): void
    {
        $jurors = [$this->makeArtisan(850), $this->makeArtisan(820), $this->makeArtisan(900)];
        app(LitigeService::class)->assignJury($this->litige);

        $expired = JuryReview::where('jure_id', $jurors[0]->id)->first();
        $expired->update(['expires_at' => now()->subHour()]);

        $this->assertSame(1, app(LitigeService::class)->expireOverdueJuryReviews());
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame(2, JuryReview::where('litige_id', $this->litige->id)->where('status', 'assigned')->count());
        $this->assertSame(1, $this->adminAlerts());
    }

    public function test_l_api_admin_refuse_un_jury_incomplet(): void
    {
        $this->makeArtisan(850);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/litiges/{$this->litige->id}/jury/assign")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
