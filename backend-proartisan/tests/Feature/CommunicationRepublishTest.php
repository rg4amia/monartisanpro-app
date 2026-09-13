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
 * Reprise d'une communication désactivée.
 *
 * Une publication clôturée était un cul-de-sac : ni modifiable, ni
 * republiable, ni supprimable. Corriger une coquille imposait de tout
 * ressaisir, médias compris. Elle redevient donc modifiable et rediffusable,
 * tandis qu'une publication **en cours** reste figée : la changer sous les
 * yeux de ceux qui la consultent serait pire que de la clôturer d'abord.
 */
class CommunicationRepublishTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'annonce',
            'titre' => 'Fermeture exceptionnelle',
            'contenu' => 'Les bureaux sont fermés lundi.',
            'cibles' => ['client', 'artisan'],
        ], $overrides);
    }

    private function closedCommunication(): Communication
    {
        $communication = $this->service()->store($this->payload(), $this->admin);
        $this->service()->publish($communication);

        return $this->service()->cloturer($communication->fresh());
    }

    public function test_a_closed_publication_can_be_edited(): void
    {
        $closed = $this->closedCommunication();

        $updated = $this->service()->update($closed, $this->payload([
            'titre' => 'Fermeture exceptionnelle — corrigée',
            'cibles' => ['client', 'artisan', 'livreur'],
        ]));

        $this->assertSame('Fermeture exceptionnelle — corrigée', $updated->titre);
        $this->assertContains('livreur', $updated->cibles_json);
        // La correction ne la remet pas en diffusion d'elle-même.
        $this->assertTrue($updated->isCloture());
    }

    public function test_a_closed_publication_can_be_republished(): void
    {
        $closed = $this->closedCommunication();
        $firstPublication = $closed->publie_at;

        $republished = $this->service()->publish($closed);

        $this->assertTrue($republished->isPublie());
        // Sans remise à zéro, la ligne serait publiée *et* marquée clôturée.
        $this->assertNull($republished->cloture_at);
        $this->assertNotNull($republished->publie_at);
        $this->assertTrue($republished->publie_at->greaterThanOrEqualTo($firstPublication));
    }

    public function test_a_republished_communication_reaches_its_targets_again(): void
    {
        $closed = $this->closedCommunication();

        $this->assertCount(0, $this->service()->getActiveForRole('client')['annonces']);

        $this->service()->publish($closed);

        $this->assertCount(1, $this->service()->getActiveForRole('client')['annonces']);
    }

    public function test_a_closed_publication_can_be_deleted(): void
    {
        $closed = $this->closedCommunication();

        $this->service()->destroy($closed);

        $this->assertDatabaseMissing('communications', ['id' => $closed->id]);
    }

    // ── Ce qui reste interdit ────────────────────────────────────────────────

    public function test_a_live_publication_cannot_be_edited(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin);
        $live = $this->service()->publish($communication);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('en cours de diffusion');

        $this->service()->update($live, $this->payload(['titre' => 'Changement à chaud']));
    }

    public function test_a_live_publication_cannot_be_published_twice(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin);
        $live = $this->service()->publish($communication);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('déjà en cours de diffusion');

        $this->service()->publish($live);
    }

    public function test_a_live_publication_cannot_be_deleted(): void
    {
        $communication = $this->service()->store($this->payload(), $this->admin);
        $live = $this->service()->publish($communication);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('en cours de diffusion');

        $this->service()->destroy($live);
    }

    // ── Média et rediffusion ─────────────────────────────────────────────────

    public function test_republishing_a_voice_message_still_requires_its_file(): void
    {
        $communication = $this->service()->store(
            $this->payload(['type' => Communication::TYPE_AUDIO]),
            $this->admin,
            UploadedFile::fake()->create('m.mp3', 100, 'audio/mpeg'),
        );
        $this->service()->publish($communication);
        $closed = $this->service()->cloturer($communication->fresh());

        // Le fichier a disparu du disque entre-temps : la rediffusion doit
        // buter dessus plutôt que renvoyer une carte muette aux utilisateurs.
        Storage::disk('public')->delete($closed->media_path);
        $closed->update(['media_path' => null]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('pas de fichier audio');

        $this->service()->publish($closed->fresh());
    }

    // ── Endpoint ─────────────────────────────────────────────────────────────

    public function test_the_endpoint_republishes_a_closed_communication(): void
    {
        $closed = $this->closedCommunication();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/communications/{$closed->id}/publish");

        $response->assertOk();
        $response->assertJsonPath('data.statut', 'publie');
        $response->assertJsonPath('data.cloture_at', null);
    }

    public function test_the_endpoint_updates_a_closed_communication(): void
    {
        $closed = $this->closedCommunication();

        $response = $this->actingAs($this->admin)->putJson(
            "/api/v1/admin/communications/{$closed->id}",
            $this->payload(['titre' => 'Titre corrigé']),
        );

        $response->assertOk();
        $response->assertJsonPath('data.titre', 'Titre corrigé');
    }
}
