<?php

namespace Tests\Feature;

use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use App\Rules\PlatformFileUrl;
use App\Support\PrivateMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chantier 42 — audit de l'application mobile du 07/10/2026, anomalie 5.
 *
 * Les photos d'une demande de mission et d'une étape s'accompagnaient de
 * n'importe quelle chaîne. L'application ouvre une adresse de vidéo hors
 * d'elle-même : un client envoyait l'artisan sur le site de son choix.
 */
class Chantier42PlatformFileUrlTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function missionPayload(array $photos): array
    {
        $sector = Sector::create(['name' => 'Bâtiment']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Plomberie']);

        return [
            'description' => 'Fuite sous l’évier de la cuisine, à réparer cette semaine.',
            'sector_id' => $sector->id,
            'trade_id' => $trade->id,
            'photos' => $photos,
        ];
    }

    public function test_seule_une_adresse_de_fichier_de_la_plateforme_est_acceptee(): void
    {
        $signed = PrivateMedia::signedUrl('missions/photo.jpg');

        // Fichiers de la plateforme.
        $this->assertTrue(PlatformFileUrl::accepts($signed));
        $this->assertTrue(PlatformFileUrl::accepts(PrivateMedia::canonicalUrl('missions/photo.jpg')));
        $this->assertTrue(PlatformFileUrl::accepts(url('/storage/uploads/photo.jpg')));
        $this->assertTrue(PlatformFileUrl::accepts('/storage/uploads/video.mp4'));

        // Tout le reste.
        $this->assertFalse(PlatformFileUrl::accepts('https://un-site.example/x.mp4'));
        $this->assertFalse(PlatformFileUrl::accepts('https://un-site.example/storage/x.mp4'));
        $this->assertFalse(PlatformFileUrl::accepts('https://un-site.example/media/prive/missions/x.mp4'));
        $this->assertFalse(PlatformFileUrl::accepts('//un-site.example/storage/x.mp4'));
        $this->assertFalse(PlatformFileUrl::accepts('https://localhost@un-site.example/storage/x.mp4'));
        $this->assertFalse(PlatformFileUrl::accepts('javascript:alert(1)'));
        $this->assertFalse(PlatformFileUrl::accepts('tel:+2250708091011'));
        $this->assertFalse(PlatformFileUrl::accepts('https://wa.me/2250708091011'));
        $this->assertFalse(PlatformFileUrl::accepts(url('/admin/login')));
        $this->assertFalse(PlatformFileUrl::accepts('/storage/../.env'));
        $this->assertFalse(PlatformFileUrl::accepts(''));
        $this->assertFalse(PlatformFileUrl::accepts(['https://un-site.example/x.mp4']));
    }

    public function test_une_demande_de_mission_refuse_une_photo_hebergee_ailleurs(): void
    {
        $client = $this->user('client');

        $this->actingAs($client)
            ->postJson('/api/v1/missions', $this->missionPayload(['https://un-site.example/x.mp4']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);

        $this->assertSame(0, Mission::count());
    }

    public function test_une_demande_de_mission_garde_une_photo_envoyee_depuis_l_application(): void
    {
        $client = $this->user('client');
        $signed = PrivateMedia::storeAndSign(UploadedFile::fake()->image('fuite.jpg'), 'missions');

        $this->actingAs($client)
            ->postJson('/api/v1/missions', $this->missionPayload([$signed]))
            ->assertSuccessful();

        $photos = Mission::firstOrFail()->photos_json;
        $this->assertCount(1, $photos);
        $this->assertSame(PrivateMedia::pathFromUrl($signed), PrivateMedia::pathFromUrl($photos[0]));
    }

    public function test_une_etape_refuse_une_photo_hebergee_ailleurs(): void
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
        $jalon = Jalon::create([
            'mission_id' => $mission->id,
            'ordre' => 1,
            'description' => 'Pose de la tuyauterie',
            'montant' => 30000,
            'statut' => 'en_attente',
        ]);

        $this->actingAs($artisan)
            ->putJson("/api/v1/jalons/{$jalon->id}/submit", [
                'photos' => [[
                    'url' => 'https://un-site.example/preuve.mp4',
                    'lat' => 5.3484,
                    'lng' => -4.0169,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0.url']);

        $this->assertSame('en_attente', $jalon->fresh()->statut);
    }
}
