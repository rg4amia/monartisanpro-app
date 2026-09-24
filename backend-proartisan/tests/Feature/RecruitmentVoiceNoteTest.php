<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Candidature par note vocale (≤ 20 s) : stockage privé, transcription
 * Gemini après la réponse HTTP et règle anti-contournement — le numéro de
 * l'artisan n'est jamais transmis au recruteur, une note qui contient des
 * coordonnées n'est donc jamais servie.
 */
class RecruitmentVoiceNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $artisan;

    private RecruitmentOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $trade = Trade::create(['sector_id' => Sector::create(['name' => 'BTP'])->id, 'name' => 'Maçonnerie']);
        $this->client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $this->offer = RecruitmentOffer::create([
            'creator_id' => $this->client->id,
            'creator_type' => 'client',
            'trade_id' => $trade->id,
            'title' => 'Renfort maçons',
            'description' => 'x',
            'mission_type' => 'journalier',
            'commune' => 'Cocody',
            'status' => 'active',
            // Accès aux candidatures déjà payé : on teste la note vocale, pas le séquestre.
            'applicants_unlocked_at' => now(),
        ]);
    }

    private function voiceNote(string $content = 'audio-sans-coordonnees'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('note.m4a', $content);
    }

    private function apply(array $payload): TestResponse
    {
        return $this->actingAs($this->artisan)->post(
            "/api/v1/recruitment-offers/{$this->offer->id}/apply",
            $payload,
            ['Accept' => 'application/json'],
        );
    }

    public function test_voice_note_is_transcribed_and_served_to_the_recruiter(): void
    {
        $this->apply(['voice_note' => $this->voiceNote(), 'voice_note_duration' => 15])->assertCreated();

        $application = RecruitmentApplication::firstOrFail();
        $this->assertSame('approved', $application->voice_status);
        $this->assertSame(15, $application->voice_note_duration);
        Storage::disk('local')->assertExists($application->voice_note_path);

        $payload = $this->actingAs($this->client)
            ->getJson("/api/v1/recruitment-offers/{$this->offer->id}/applications")
            ->assertOk()
            ->json('data.0');

        $this->assertStringContainsString('Transcription simulée', $payload['voice_transcription']);
        $this->assertArrayNotHasKey('voice_note_path', $payload);
        $this->assertNotNull($payload['voice_note_url']);

        $this->get($payload['voice_note_url'])->assertOk();
    }

    public function test_voice_note_with_contact_details_is_never_transmitted(): void
    {
        $this->apply(['voice_note' => $this->voiceNote('appelez-moi, voici mon contact'), 'voice_note_duration' => 12])->assertCreated();

        $application = RecruitmentApplication::firstOrFail();
        $this->assertSame('contact_detected', $application->voice_status);
        $this->assertNull($application->voice_note_path);
        $this->assertNull($application->voice_note_url);
        $this->assertCount(0, Storage::disk('local')->allFiles('recruitment/voice-notes'));
        $this->assertTrue(Notification::where('user_id', $this->artisan->id)->where('title', 'Note vocale non transmise')->exists());

        // La candidature elle-même reste valable.
        $this->assertSame('submitted', $application->status);
    }

    public function test_unavailable_transcription_withholds_the_voice_note(): void
    {
        $this->mock(GeminiService::class)->shouldReceive('transcribeRecruitmentVoiceNote')->andReturnNull();

        $this->apply(['voice_note' => $this->voiceNote(), 'voice_note_duration' => 10])->assertCreated();

        $application = RecruitmentApplication::firstOrFail();
        $this->assertSame('failed', $application->voice_status);
        $this->assertNull($application->voice_note_url);
        $this->assertNull($application->voice_transcription);
    }

    public function test_voice_note_is_optional(): void
    {
        $this->apply([])->assertCreated();

        $application = RecruitmentApplication::firstOrFail();
        $this->assertNull($application->voice_status);
        $this->assertNull($application->voice_note_url);
    }

    public function test_voice_note_longer_than_twenty_seconds_or_one_megabyte_is_refused(): void
    {
        $this->apply(['voice_note' => $this->voiceNote(), 'voice_note_duration' => 21])->assertStatus(422);
        $this->apply(['voice_note' => UploadedFile::fake()->create('note.m4a', 2048), 'voice_note_duration' => 15])->assertStatus(422);
        $this->apply(['voice_note' => $this->voiceNote()])->assertStatus(422);

        $this->assertSame(0, RecruitmentApplication::count());
    }

    public function test_voice_note_file_requires_a_valid_signature(): void
    {
        $this->apply(['voice_note' => $this->voiceNote(), 'voice_note_duration' => 15])->assertCreated();
        $application = RecruitmentApplication::firstOrFail();

        $this->get("/recruitment/voice-notes/{$application->id}/file")->assertForbidden();
    }
}
