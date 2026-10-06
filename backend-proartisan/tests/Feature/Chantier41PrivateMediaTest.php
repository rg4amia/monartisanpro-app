<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionMessage;
use App\Models\User;
use App\Support\PrivateMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chantier 41 — décision du 06/10/2026 : seules les images de catalogue et
 * les réalisations d'un artisan sont publiques. Tout autre fichier d'un
 * utilisateur vit sur le disque privé et se lit par lien signé.
 */
class Chantier41PrivateMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

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

    private function withoutSignature(string $url): string
    {
        return strtok($url, '?');
    }

    // ── Discussion de chantier ──────────────────────────────────────────────

    public function test_la_photo_d_une_discussion_n_est_lisible_que_par_son_lien_signe(): void
    {
        [$client, $artisan, $mission] = $this->missionWithParticipants();

        $sent = $this->actingAs($client)
            ->post("/api/v1/missions/{$mission->id}/messages", [
                'file' => UploadedFile::fake()->image('carrelage.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.media_url');

        // Rien sur le disque public, le fichier est sur le disque privé.
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertCount(1, Storage::disk('local')->allFiles('media/chat'));

        // En base : l'adresse canonique, sans signature.
        $stored = DB::table('mission_messages')->value('media_url');
        $this->assertStringContainsString('/media/prive/chat/', $stored);
        $this->assertStringNotContainsString('signature=', $stored);

        // L'adresse canonique seule est refusée ; le lien signé sert le fichier.
        $this->get($stored)->assertStatus(403);
        $this->get($sent)->assertOk();

        // L'autre participant reçoit un lien signé à la lecture des messages.
        $listed = $this->actingAs($artisan)
            ->getJson("/api/v1/missions/{$mission->id}/messages")
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('signature=', $listed);
    }

    public function test_un_lien_signe_expire(): void
    {
        [$client, , $mission] = $this->missionWithParticipants();

        $url = $this->actingAs($client)
            ->post("/api/v1/missions/{$mission->id}/messages", [
                'file' => UploadedFile::fake()->image('carrelage.jpg'),
            ], ['Accept' => 'application/json'])
            ->json('data.media_url');

        $this->travel((int) config('prosartisan.private_media.url_ttl_minutes') + 1)->minutes();

        $this->get($url)->assertStatus(403);
    }

    // ── Écriture : seule une signature valide fait entrer une adresse ───────

    public function test_une_adresse_privee_sans_signature_valide_n_est_pas_enregistree(): void
    {
        [, , $mission] = $this->missionWithParticipants();

        $path = PrivateMedia::store(UploadedFile::fake()->image('a.jpg'), 'fileshare');
        $other = PrivateMedia::store(UploadedFile::fake()->image('b.jpg'), 'fileshare');
        $expired = PrivateMedia::signedUrl($other);

        $this->travel((int) config('prosartisan.private_media.url_ttl_minutes') + 1)->minutes();

        $mission->update(['photos_json' => [
            PrivateMedia::signedUrl($path),                  // lien valide : accepté
            PrivateMedia::canonicalUrl($other),              // adresse recopiée sans signature : refusée
            $expired,                                         // lien périmé : refusé
            'https://prosartisan.net/storage/fileshare/ancien.jpg', // ancienne adresse publique : conservée
            url('/media/prive/../../.env'),                  // chemin refusé
        ]]);

        $stored = json_decode(DB::table('missions')->where('id', $mission->id)->value('photos_json'), true);

        $this->assertSame([
            PrivateMedia::canonicalUrl($path),
            'https://prosartisan.net/storage/fileshare/ancien.jpg',
        ], $stored);

        // À la lecture : lien signé pour le fichier privé, adresse publique inchangée.
        $read = $mission->fresh()->photos_json;
        $this->assertStringContainsString('signature=', $read[0]);
        $this->assertSame('https://prosartisan.net/storage/fileshare/ancien.jpg', $read[1]);

        // Réenregistrer ce qui vient d'être lu ne perd rien.
        $mission->fresh()->update(['photos_json' => $read]);
        $this->assertCount(2, $mission->fresh()->photos_json);
    }

    public function test_une_adresse_deja_enregistree_survit_a_un_reenregistrement_tardif(): void
    {
        [, , $mission] = $this->missionWithParticipants();

        $path = PrivateMedia::store(UploadedFile::fake()->image('a.jpg'), 'chat/1');
        $message = MissionMessage::create([
            'mission_id' => $mission->id,
            'sender_id' => $mission->client_id,
            'type' => 'image',
            'content' => '',
            'media_url' => PrivateMedia::signedUrl($path),
        ]);

        $read = $message->fresh()->media_url;
        $this->travel((int) config('prosartisan.private_media.url_ttl_minutes') + 1)->minutes();

        // Le lien lu a expiré, mais il désigne le fichier déjà attaché.
        $message->fresh()->update(['media_url' => $read]);

        $this->assertSame(PrivateMedia::canonicalUrl($path), DB::table('mission_messages')->value('media_url'));
    }

    // ── Téléversement générique ─────────────────────────────────────────────

    public function test_la_photo_d_une_demande_de_mission_part_sur_le_disque_prive(): void
    {
        $url = $this->actingAs($this->user('client'))
            ->postJson('/api/v1/upload', ['file' => UploadedFile::fake()->image('fuite.jpg')])
            ->assertOk()
            ->json('url');

        $this->assertStringContainsString('/media/prive/fileshare/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get($this->withoutSignature($url))->assertStatus(403);
        $this->get($url)->assertOk();
    }

    public function test_l_image_de_catalogue_d_un_fournisseur_reste_publique(): void
    {
        $url = $this->actingAs($this->user('fournisseur'))
            ->postJson('/api/v1/upload', ['file' => UploadedFile::fake()->image('product.jpg')])
            ->assertOk()
            ->json('url');

        $this->assertStringContainsString('/storage/fileshare/', $url);
        $this->assertCount(1, Storage::disk('public')->allFiles('fileshare'));
    }

    public function test_un_client_ne_publie_pas_en_annoncant_un_usage_catalogue(): void
    {
        $url = $this->actingAs($this->user('client'))
            ->postJson('/api/v1/upload', [
                'file' => UploadedFile::fake()->image('photo.jpg'),
                'usage' => 'catalogue',
            ])
            ->assertOk()
            ->json('url');

        $this->assertStringContainsString('/media/prive/', $url);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ── Route de lecture ────────────────────────────────────────────────────

    public function test_un_chemin_qui_remonte_les_dossiers_n_est_jamais_servi(): void
    {
        Storage::disk('local')->put('secret.txt', 'secret');

        foreach (['../secret.txt', 'chat/../../secret.txt', 'secret.txt', '.env'] as $path) {
            $this->assertFalse(PrivateMedia::isSafePath($path), $path);
            $this->assertFalse(PrivateMedia::exists($path), $path);
        }

        $this->get(PrivateMedia::signedUrl('chat/1/absent.jpg'))->assertStatus(404);
    }
}
