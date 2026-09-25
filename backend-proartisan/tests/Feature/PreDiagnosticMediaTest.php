<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use App\Services\AiMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PreDiagnosticMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $clientActive;
    private User $clientPending;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientActive = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $this->clientPending = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'en_attente',
        ]);
    }

    public function test_kyc_required_for_pre_diagnostic(): void
    {
        $response = $this->actingAs($this->clientPending)
            ->postJson('/api/v1/missions/pre-diagnostic', [
                'description' => 'Fuite importante sous évier cuisine',
            ]);

        $response->assertStatus(403);
    }

    public function test_requires_at_least_media_or_description(): void
    {
        $response = $this->actingAs($this->clientActive)
            ->postJson('/api/v1/missions/pre-diagnostic', []);

        $response->assertStatus(422);
    }

    public function test_pre_diagnostic_with_photos(): void
    {
        $photo1 = UploadedFile::fake()->image('fuite1.jpg', 600, 600);
        $photo2 = UploadedFile::fake()->image('fuite2.png', 600, 600);

        $response = $this->actingAs($this->clientActive)
            ->post('/api/v1/missions/pre-diagnostic', [
                'description' => 'Grosse fuite d\'eau sous évier avec inondation',
                'category' => 'Plomberie',
                'photos' => [$photo1, $photo2],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'diagnostic_summary',
                'severity',
                'recommended_trade',
                'recommended_intervention_type',
                'key_visual_clues',
                'urgency_precautions',
                'estimated_materials' => [
                    '*' => ['name', 'estimated_price'],
                ],
                'pricing' => [
                    'materials_min',
                    'materials_max',
                    'labor_min',
                    'labor_max',
                    'total_min',
                    'total_max',
                ],
                'analyzed_at',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals('Plomberie', $data['recommended_trade']);
        $this->assertNotEmpty($data['urgency_precautions']);
        $this->assertIsInt($data['pricing']['total_min']);
        $this->assertIsInt($data['pricing']['total_max']);
    }

    public function test_pre_diagnostic_with_video(): void
    {
        $video = UploadedFile::fake()->create('sinistre.mp4', 1500, 'video/mp4');

        $response = $this->actingAs($this->clientActive)
            ->post('/api/v1/missions/pre-diagnostic', [
                'description' => 'Disjoncteur qui saute et court-circuit étincelles',
                'category' => 'Électricité',
                'video' => $video,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('Électricité', $data['recommended_trade']);
        $this->assertEquals('urgent', $data['severity']);
    }

    public function test_pre_diagnostic_blocks_when_quota_exceeded(): void
    {
        // Insérer une limite journalière de 1 appel IA pour cet utilisateur
        DB::table('ai_user_quotas')->insert([
            'user_id' => $this->clientActive->id,
            'daily_limit' => 1,
            'blocked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simuler 1 appel existant aujourd'hui
        AiMonitoringService::log(
            'gemini-3.6-flash',
            'pre_diagnostic',
            100,
            200,
            250.0,
            200,
            null,
            $this->clientActive->id
        );

        $response = $this->actingAs($this->clientActive)
            ->post('/api/v1/missions/pre-diagnostic', [
                'description' => 'Fuite robinet salle de bain',
            ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_mission_creation_stores_diagnostic_media_analysis(): void
    {
        $analysisPayload = [
            'diagnostic_summary' => 'Fuite détectée sur flexible de raccordement évier.',
            'severity' => 'moyen',
            'recommended_trade' => 'Plomberie',
            'recommended_intervention_type' => 'Dépannage',
            'key_visual_clues' => ['Goutte à goutte continu', 'Joint usé'],
            'urgency_precautions' => ['Fermer le robinet d\'arrêt'],
            'estimated_materials' => [
                ['name' => 'Flexible inox 3/8', 'estimated_price' => 4500],
            ],
            'pricing' => [
                'materials_min' => 4500,
                'materials_max' => 9000,
                'labor_min' => 10000,
                'labor_max' => 15000,
                'total_min' => 14500,
                'total_max' => 24000,
            ],
        ];

        $response = $this->actingAs($this->clientActive)
            ->postJson('/api/v1/missions', [
                'description' => 'Réparation complète de la tuyauterie et flexible sous évier de cuisine.',
                'diagnostic_media_analysis' => $analysisPayload,
            ]);

        $response->assertStatus(201);
        $missionId = $response->json('data.id');

        $this->assertDatabaseHas('missions', [
            'id' => $missionId,
            'client_id' => $this->clientActive->id,
        ]);

        $mission = Mission::find($missionId);
        $this->assertNotNull($mission->diagnostic_media_analysis);
        $this->assertEquals('Plomberie', $mission->diagnostic_media_analysis['recommended_trade']);
        $this->assertEquals(
            'Fuite détectée sur flexible de raccordement évier.',
            $mission->diagnostic_media_analysis['diagnostic_summary']
        );

        // Vérifier également la présence dans MissionResource
        $response->assertJsonPath('data.diagnosticMediaAnalysis.recommended_trade', 'Plomberie');
    }
}
