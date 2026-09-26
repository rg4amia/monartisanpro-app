<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoiceQuoteTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create([
            'role' => 'client',
            'kyc_status' => 'actif',
        ]);

        $this->artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $this->mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $this->artisan->id,
            'description' => 'Réparation fuite d\'eau sous évier et remplacement siphon cuisine.',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.00,
        ]);
    }

    private function createAudioFile(string $filename = 'devis_audio.m4a'): UploadedFile
    {
        return UploadedFile::fake()->create($filename, 120, 'audio/m4a');
    }

    public function test_client_cannot_request_voice_quote(): void
    {
        $response = $this->actingAs($this->client)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis/voice-quote", [
                'audio' => $this->createAudioFile(),
            ]);

        $response->assertStatus(403);
    }

    public function test_unverified_kyc_artisan_is_blocked_by_middleware(): void
    {
        $unverifiedArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'en_attente',
        ]);

        $mission = Mission::create([
            'client_id' => $this->client->id,
            'artisan_id' => $unverifiedArtisan->id,
            'description' => 'Travaux de peinture',
            'status' => 'draft',
            'montant_total' => 0,
            'montant_materiaux' => 0,
            'montant_mo' => 0,
            'ratio_materiaux' => 0.00,
        ]);

        $response = $this->actingAs($unverifiedArtisan)
            ->postJson("/api/v1/missions/{$mission->id}/devis/voice-quote", [
                'audio' => $this->createAudioFile(),
            ]);

        $response->assertStatus(403);
    }

    public function test_artisan_cannot_request_voice_quote_for_mission_assigned_to_another_artisan(): void
    {
        $anotherArtisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
        ]);

        $response = $this->actingAs($anotherArtisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis/voice-quote", [
                'audio' => $this->createAudioFile(),
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cette mission est réservée à un autre artisan.');
    }

    public function test_voice_quote_requires_audio_file(): void
    {
        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis/voice-quote", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }

    public function test_voice_quote_blocked_when_daily_quota_is_reached(): void
    {
        DB::table('ai_settings')->where('key', 'daily_user_limit')->update(['value' => '2']);

        for ($i = 0; $i < 2; $i++) {
            DB::table('ai_usage_logs')->insert([
                'user_id' => $this->artisan->id,
                'model_name' => 'gemini-3.6-flash',
                'action_type' => 'voice_quote',
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
                'response_time_ms' => 100,
                'status_code' => 200,
                'estimated_cost_usd' => 0.001,
                'created_at' => now()->subMinutes($i + 1),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis/voice-quote", [
                'audio' => $this->createAudioFile(),
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_artisan_receives_balanced_voice_quote_suggestion(): void
    {
        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis/voice-quote", [
                'audio' => $this->createAudioFile(),
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transcription',
                    'lignes' => [
                        '*' => ['type', 'description', 'montant', 'source'],
                    ],
                    'jalons' => [
                        '*' => ['ordre', 'description', 'montant', 'date_cible'],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['transcription']);

        $totalLignes = collect($data['lignes'])->sum('montant');
        $totalJalons = collect($data['jalons'])->sum('montant');

        $this->assertGreaterThan(0, $totalLignes);
        $this->assertEquals($totalLignes, $totalJalons, 'Le montant total des lignes doit être exactement égal au montant total des jalons');
    }

    public function test_gemini_service_balances_discrepancies_between_lignes_and_jalons(): void
    {
        $gemini = app(GeminiService::class);
        $reflection = new \ReflectionClass($gemini);
        $method = $reflection->getMethod('formatAndBalanceSuggestion');
        $method->setAccessible(true);

        $mockGeminiOutput = [
            'lignes' => [
                ['type' => 'mat', 'description' => 'Tuyau PVC', 'montant' => 15000],
                ['type' => 'mo', 'description' => 'Pose tuyau', 'montant' => 20000],
            ],
            'jalons' => [
                ['ordre' => 1, 'description' => 'Acompte', 'montant' => 10000, 'date_cible_days' => 2],
                ['ordre' => 2, 'description' => 'Fin travaux', 'montant' => 20000, 'date_cible_days' => 5],
            ],
        ];

        // Lignes total = 35000, Jalons initial total = 30000 (écart de +5000)
        $balanced = $method->invoke($gemini, $mockGeminiOutput);

        $totalLignes = collect($balanced['lignes'])->sum('montant');
        $totalJalons = collect($balanced['jalons'])->sum('montant');

        $this->assertEquals(35000, $totalLignes);
        $this->assertEquals(35000, $totalJalons);
        // Le dernier jalon doit absorber la différence : 20000 + 5000 = 25000
        $this->assertEquals(25000, $balanced['jalons'][1]['montant']);
    }

    public function test_voice_quote_accepts_text_transcript(): void
    {
        $response = $this->actingAs($this->artisan)
            ->postJson("/api/v1/missions/{$this->mission->id}/devis/voice-quote", [
                'transcript' => 'Ya dra sur le tuyau sous évier, faut 2 tuyaux PVC 40, de la colle Tangit et 1 siphon.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transcription', 'Ya dra sur le tuyau sous évier, faut 2 tuyaux PVC 40, de la colle Tangit et 1 siphon.');
    }

    public function test_voice_quote_prompt_contains_nouchi_and_ivorian_btp_lexicon(): void
    {
        $gemini = app(GeminiService::class);
        $prompt = $gemini->buildVoiceQuotePrompt($this->mission);

        $this->assertStringContainsString('nouchi', $prompt);
        $this->assertStringContainsString('ya dra', $prompt);
        $this->assertStringContainsString('fer de 12', $prompt);
        $this->assertStringContainsString('CPJ', $prompt);
        $this->assertStringContainsString('Tangit', $prompt);
        $this->assertStringContainsString('BIGINT', $prompt);
    }
}
