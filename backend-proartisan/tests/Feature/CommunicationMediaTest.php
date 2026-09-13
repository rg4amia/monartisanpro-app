<?php

namespace Tests\Feature;

use App\Models\Communication;
use App\Models\User;
use App\Services\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Diffusion de contenus vocaux et vidéo aux quatre espaces.
 *
 * L'audio est téléversé sur le disque public ; la vidéo est référencée par
 * une URL externe, héberger de la vidéo sur un mutualisé épuisant quota et
 * bande passante. Les deux se ciblent par rôle comme les annonces existantes.
 */
class CommunicationMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function service(): CommunicationService
    {
        return app(CommunicationService::class);
    }

    private function audioFile(): UploadedFile
    {
        return UploadedFile::fake()->create('message.mp3', 240, 'audio/mpeg');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => Communication::TYPE_AUDIO,
            'titre' => 'Message du jour',
            'contenu' => 'Résumé écrit du message vocal.',
            'cibles' => ['client', 'artisan', 'fournisseur', 'livreur'],
        ], $overrides);
    }

    // ── Création ─────────────────────────────────────────────────────────────

    public function test_a_voice_publication_stores_its_file_and_exposes_a_playable_url(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin, $this->audioFile());

        $this->assertNotNull($communication->media_path);
        Storage::disk('public')->assertExists($communication->media_path);

        // Le mobile lit une clé unique, sans connaître la provenance du média.
        $this->assertStringContainsString($communication->media_path, (string) $communication->media_url);
        $this->assertSame(240 * 1024, $communication->media_size);
        $this->assertTrue($communication->hasPlayableMedia());
    }

    public function test_a_video_publication_keeps_its_external_link_and_stores_no_file(): void
    {
        $communication = $this->service()->store(
            $this->payload([
                'type' => Communication::TYPE_VIDEO,
                'media_external_url' => 'https://www.youtube.com/watch?v=abc123',
            ]),
            $this->admin,
        );

        $this->assertNull($communication->media_path);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $communication->media_url);
    }

    // ── Cycle de vie du fichier ──────────────────────────────────────────────

    public function test_changing_the_type_releases_the_stored_audio(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin, $this->audioFile());
        $path = $communication->media_path;

        // Devenue vidéo, la publication n'affichera plus jamais ce fichier :
        // le garder consommerait du quota sans que rien ne le supprime.
        $updated = $this->service()->update(
            $communication,
            $this->payload([
                'type' => Communication::TYPE_VIDEO,
                'media_external_url' => 'https://vimeo.com/123456',
            ]),
        );

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($updated->media_path);
    }

    public function test_replacing_the_audio_removes_the_previous_file(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin, $this->audioFile());
        $first = $communication->media_path;

        $updated = $this->service()->update($communication, $this->payload(), $this->audioFile());

        $this->assertNotSame($first, $updated->media_path);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($updated->media_path);
    }

    public function test_deleting_a_draft_removes_its_audio_file(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin, $this->audioFile());
        $path = $communication->media_path;

        $this->service()->destroy($communication);

        Storage::disk('public')->assertMissing($path);
    }

    // ── Publication ──────────────────────────────────────────────────────────

    public function test_a_voice_publication_without_a_file_cannot_be_published(): void
    {
        // Sans ce garde-fou, la carte partirait inerte vers tous les espaces.
        $communication = $this->service()->store($this->payload(), $this->admin);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('pas de fichier audio');

        $this->service()->publish($communication);
    }

    public function test_a_video_publication_without_a_link_cannot_be_published(): void
    {
        $communication = $this->service()->store(
            $this->payload(['type' => Communication::TYPE_VIDEO]),
            $this->admin,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('pas de lien');

        $this->service()->publish($communication);
    }

    // ── Diffusion par rôle ───────────────────────────────────────────────────

    public function test_each_role_receives_the_media_targeted_at_it(): void
    {
        $voice = $this->service()->store(
            $this->payload(['cibles' => ['livreur']]),
            $this->admin,
            $this->audioFile(),
        );
        $this->service()->publish($voice);

        $video = $this->service()->store(
            $this->payload([
                'type' => Communication::TYPE_VIDEO,
                'cibles' => ['client', 'livreur'],
                'media_external_url' => 'https://www.youtube.com/watch?v=xyz',
            ]),
            $this->admin,
        );
        $this->service()->publish($video);

        $driverFeed = $this->service()->getActiveForRole('livreur');
        $this->assertCount(1, $driverFeed['audio']);
        $this->assertCount(1, $driverFeed['video']);

        // Le client n'était pas ciblé par le message vocal : il ne le voit pas.
        $clientFeed = $this->service()->getActiveForRole('client');
        $this->assertCount(0, $clientFeed['audio']);
        $this->assertCount(1, $clientFeed['video']);
    }

    public function test_a_media_whose_file_vanished_is_not_broadcast(): void
    {
        $voice = $this->service()->store($this->payload(), $this->admin, $this->audioFile());
        $this->service()->publish($voice);

        // Fichier effacé du disque hors de l'application : la carte serait
        // affichée mais illisible.
        $voice->update(['media_path' => null]);

        $this->assertCount(0, $this->service()->getActiveForRole('client')['audio']);
    }

    // ── Endpoint mobile ──────────────────────────────────────────────────────

    public function test_the_mobile_feed_carries_the_two_new_sections(): void
    {
        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $response = $this->actingAs($client)->getJson('/api/v1/communications/active');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['annonces', 'le_saviez_vous', 'audio', 'video'],
        ]);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_a_video_link_in_cleartext_is_refused(): void
    {
        // Le manifeste Android de production interdit le trafic en clair : une
        // URL `http://` ne se chargerait pas dans l'application publiée. Mieux
        // vaut la refuser à la saisie que diffuser une vidéo injouable.
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/communications', $this->payload([
            'type' => Communication::TYPE_VIDEO,
            'media_external_url' => 'http://www.youtube.com/watch?v=abc',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('media_external_url');
    }

    public function test_a_voice_publication_requires_a_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/communications', $this->payload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('media_file');
    }

    public function test_a_non_audio_file_is_refused(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/v1/admin/communications', $this->payload([
            'media_file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('media_file');
    }
}
