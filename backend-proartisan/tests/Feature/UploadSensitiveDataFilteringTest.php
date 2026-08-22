<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadSensitiveDataFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_allows_clean_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        $file = UploadedFile::fake()->image('clean_image.jpg');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/upload', [
                'file' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'message', 'url']);
    }

    public function test_upload_rejects_sensitive_media_containing_contacts_or_locations(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);

        // GeminiService's test simulation rejects if filename contains "sensitive", "contact", or "address"
        $file = UploadedFile::fake()->image('sensitive_leakage.jpg');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonFragment([
            'message' => 'Fichier rejeté : Le fichier contient des indications interdites (détecté par simulation).',
        ]);
    }
}
