<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionMessage;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chantier 37 — trois failles critiques de l'audit du 06/10/2026, ouvertes à
 * tout compte connecté :
 * - les routes SMS de l'API (envoi libre, lecture des messages envoyés) ;
 * - la gestion des codes promo par l'API, sans contrôle d'administrateur ;
 * - la messagerie de chantier, qui publiait n'importe quel fichier avec
 *   l'extension choisie par l'expéditeur.
 */
class Chantier37CriticalApiFixesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif']);
    }

    /**
     * @return array{0: User, 1: User, 2: Mission}
     */
    private function missionWithParticipants(): array
    {
        $client = $this->user('client');
        $artisan = $this->user('artisan');

        $mission = Mission::create([
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

        return [$client, $artisan, $mission];
    }

    private function promoCode(): PromoCode
    {
        return PromoCode::create([
            'code' => 'RENTREE10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);
    }

    // ── Routes SMS ──────────────────────────────────────────────────────────

    public function test_aucun_compte_n_envoie_de_sms_libre_par_l_api(): void
    {
        foreach (['client', 'artisan', 'fournisseur', 'livreur', 'admin'] as $role) {
            $status = $this->actingAs($this->user($role))
                ->postJson('/api/v1/sms/send', ['recipient' => '+2250700000000', 'message' => 'Votre paiement est reçu.'])
                ->status();

            $this->assertContains($status, [404, 405], "Le rôle {$role} atteint encore l'envoi de SMS.");
        }
    }

    public function test_aucun_compte_ne_lit_les_sms_envoyes_par_l_api(): void
    {
        $client = $this->user('client');

        $this->assertContains($this->actingAs($client)->getJson('/api/v1/sms')->status(), [404, 405]);
        $this->assertContains($this->actingAs($client)->getJson('/api/v1/sms/abc123')->status(), [404, 405]);
    }

    // ── Codes promo ─────────────────────────────────────────────────────────

    public function test_un_utilisateur_ne_cree_pas_de_code_promo_par_l_api(): void
    {
        $status = $this->actingAs($this->user('client'))
            ->postJson('/api/v1/promo-codes', [
                'code' => 'GRATUIT',
                'discount_type' => 'percent',
                'discount_value' => 100,
            ])
            ->status();

        $this->assertContains($status, [403, 404, 405]);
        $this->assertSame(0, PromoCode::where('code', 'GRATUIT')->count());
    }

    public function test_un_utilisateur_ne_liste_ni_ne_modifie_les_codes_promo_par_l_api(): void
    {
        $promo = $this->promoCode();
        $artisan = $this->user('artisan');

        $this->assertContains($this->actingAs($artisan)->getJson('/api/v1/promo-codes')->status(), [403, 404, 405]);

        $this->assertContains(
            $this->actingAs($artisan)->putJson("/api/v1/promo-codes/{$promo->id}", [
                'code' => 'RENTREE10',
                'discount_type' => 'percent',
                'discount_value' => 100,
            ])->status(),
            [403, 404, 405]
        );
        $this->assertContains($this->actingAs($artisan)->postJson("/api/v1/promo-codes/{$promo->id}/toggle")->status(), [403, 404, 405]);
        $this->assertContains($this->actingAs($artisan)->deleteJson("/api/v1/promo-codes/{$promo->id}")->status(), [403, 404, 405]);

        $promo->refresh();
        $this->assertSame(10, (int) $promo->discount_value);
        $this->assertTrue((bool) $promo->is_active);
    }

    public function test_la_verification_d_un_code_promo_reste_ouverte(): void
    {
        $this->promoCode();

        $this->postJson('/api/v1/promo-codes/verify', ['code' => 'rentree10', 'amount' => 10000])
            ->assertOk()
            ->assertJsonPath('data.discount_amount', 1000);
    }

    // ── Messagerie de chantier ──────────────────────────────────────────────

    public function test_un_fichier_executable_est_refuse_dans_la_messagerie(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$client, , $mission] = $this->missionWithParticipants();

        foreach (['porte.php', 'page.html', 'image.svg', 'archive.zip'] as $name) {
            $this->actingAs($client)
                ->post("/api/v1/missions/{$mission->id}/messages", [
                    'file' => UploadedFile::fake()->createWithContent($name, '<?php echo 1; ?>'),
                ], ['Accept' => 'application/json'])
                ->assertStatus(422);
        }

        $this->assertSame(0, MissionMessage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_l_extension_enregistree_ne_vient_jamais_du_nom_du_fichier(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$client, , $mission] = $this->missionWithParticipants();

        // Une vraie image, présentée sous un nom en .php.
        $image = UploadedFile::fake()->image('photo.jpg');
        $disguised = new UploadedFile($image->getPathname(), 'chantier.php', 'image/jpeg', null, true);

        $this->actingAs($client)
            ->post("/api/v1/missions/{$mission->id}/messages", ['file' => $disguised], ['Accept' => 'application/json'])
            ->assertCreated();

        // Chantier 41 : les médias de discussion vivent sur le disque privé.
        $this->assertSame([], Storage::disk('public')->allFiles());
        $files = Storage::disk('local')->allFiles('media/chat');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.jpg', $files[0]);
        $this->assertSame('image', MissionMessage::sole()->type);
    }

    public function test_une_photo_et_une_note_vocale_passent_toujours(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$client, $artisan, $mission] = $this->missionWithParticipants();

        $this->actingAs($client)
            ->post("/api/v1/missions/{$mission->id}/messages", [
                'file' => UploadedFile::fake()->image('carrelage.png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'image');

        // Note vocale de l'application : conteneur MP4, extension .m4a.
        $this->actingAs($artisan)
            ->post("/api/v1/missions/{$mission->id}/messages", [
                'file' => UploadedFile::fake()->create('chat_memo_1.m4a', 40, 'audio/mp4'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'audio');

        // Chantier 41 : les médias de discussion vivent sur le disque privé.
        $this->assertSame([], Storage::disk('public')->allFiles());
        $files = Storage::disk('local')->allFiles('media/chat');
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            $this->assertMatchesRegularExpression('/\.(png|m4a)$/', $file);
        }
    }
}
