<?php

namespace Tests\Feature;

use App\Exceptions\PaymentException;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Permission;
use App\Models\User;
use App\Support\UserFacingError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chantier 40 — suites de l'audit du 06/10/2026 sur l'état du projet après le
 * Chantier 39.
 */
class Chantier40AuditFollowUpsTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * @return array{0: Mission, 1: Litige, 2: User}
     */
    private function disputedMission(int $total = 300000): array
    {
        $client = $this->user('client');
        $artisan = $this->user('artisan');

        $mission = Mission::create([
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'description' => 'Réfection de la salle de bain',
            'status' => 'in_progress',
            'montant_total' => $total,
            'montant_materiaux' => (int) ($total * 0.65),
            'montant_mo' => $total - (int) ($total * 0.65),
            'ratio_materiaux' => 0.65,
            'referent_required' => false,
        ]);

        $litige = Litige::create([
            'mission_id' => $mission->id,
            'declencheur_id' => $client->id,
            'type' => 'client',
            'motif' => 'malfaçon',
            'description' => 'Fissures anormales constatées après ragréage.',
            'statut' => 'ouvert',
            'workflow_step' => 'instruction',
        ]);

        return [$mission, $litige, $artisan];
    }

    // ── Anomalie 2 : origines autorisées à appeler l'API ────────────────────

    public function test_le_site_public_appelle_l_api_depuis_chacune_de_ses_adresses(): void
    {
        foreach (['https://prosartisan.net', 'https://www.prosartisan.net', 'https://www.prosartisan.ci'] as $origin) {
            $this->withHeaders(['Origin' => $origin])
                ->getJson('/api/v1/settings/app-access')
                ->assertOk()
                ->assertHeader('Access-Control-Allow-Origin', $origin);

            $this->call('OPTIONS', '/api/v1/vitrine/whatsapp-click', [], [], [], [
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ])->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_une_origine_etrangere_n_est_pas_autorisee(): void
    {
        foreach (['https://evil.example', 'https://www.prosartisan.net.evil.example', 'http://www.prosartisan.net'] as $origin) {
            $response = $this->withHeaders(['Origin' => $origin])->getJson('/api/v1/settings/app-access');

            $this->assertNull($response->headers->get('Access-Control-Allow-Origin'), $origin);
        }
    }

    // ── Anomalie 5 : capacité fine, jamais le seul rôle ─────────────────────

    public function test_un_administrateur_restreint_ne_lit_ni_litige_ni_discussion_ni_passeport(): void
    {
        [$mission, $litige, $artisan] = $this->disputedMission();
        $admin = $this->restrictedAdmin(['admin.faq.manage']);

        $this->actingAs($admin)->getJson("/api/v1/litiges/{$litige->id}")->assertStatus(403);
        $this->actingAs($admin)->getJson("/api/v1/missions/{$mission->id}/messages")->assertStatus(403);
        $this->actingAs($admin)->getJson("/api/v1/missions/{$mission->id}/devis")->assertStatus(403);
        $this->actingAs($admin)->getJson("/api/v1/solvency-passports/{$artisan->id}")->assertStatus(403);
        $this->actingAs($admin)->postJson("/api/v1/litiges/{$litige->id}/jury/assign")->assertStatus(403);
    }

    public function test_un_administrateur_habilite_garde_ses_acces(): void
    {
        [$mission, $litige, $artisan] = $this->disputedMission();

        $this->actingAs($this->restrictedAdmin(['admin.litiges.view']))
            ->getJson("/api/v1/litiges/{$litige->id}")->assertOk();
        $this->actingAs($this->restrictedAdmin(['admin.missions.view']))
            ->getJson("/api/v1/missions/{$mission->id}/messages")->assertOk();
        $this->actingAs($this->restrictedAdmin(['admin.users.view']))
            ->getJson("/api/v1/solvency-passports/{$artisan->id}")->assertOk();
    }

    public function test_un_referent_ne_lit_que_la_discussion_d_un_chantier_de_son_ressort(): void
    {
        [$ordinary] = $this->disputedMission();
        [$aboveThreshold] = $this->disputedMission(total: 2500000);
        $referent = $this->user('referent');

        $this->actingAs($referent)->getJson("/api/v1/missions/{$ordinary->id}/messages")->assertStatus(403);
        $this->actingAs($referent)->getJson("/api/v1/missions/{$aboveThreshold->id}/messages")->assertOk();
    }

    // ── Anomalie 6 : aucun message technique à l'écran ──────────────────────

    public function test_un_refus_metier_garde_son_message_une_erreur_technique_est_remplacee(): void
    {
        $fallback = "La livraison n'a pas pu être validée. Réessayez.";

        $this->assertSame('Code de réception incorrect.', UserFacingError::message(new \Exception('Code de réception incorrect.'), $fallback));
        $this->assertSame('Plafond dépassé.', UserFacingError::message(new PaymentException('Plafond dépassé.'), $fallback));

        foreach ([
            new \RuntimeException('SQLSTATE[42S22]: Column not found: 1054 Unknown column'),
            new \TypeError('Argument #1 ($order) must be of type Order, null given'),
            new \Exception(''),
        ] as $technical) {
            $this->assertSame($fallback, UserFacingError::message($technical, $fallback));
        }
    }

    // ── Anomalie 4 : téléversement générique ────────────────────────────────

    public function test_le_televersement_generique_refuse_un_pdf(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user('client'))
            ->postJson('/api/v1/upload', ['file' => UploadedFile::fake()->create('devis.pdf', 120, 'application/pdf')])
            ->assertStatus(422);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
