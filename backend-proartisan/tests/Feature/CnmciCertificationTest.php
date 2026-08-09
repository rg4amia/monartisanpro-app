<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CnmciCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_can_submit_cnmci_information()
    {
        Storage::fake('public');

        $artisan = User::factory()->create([
            'role' => 'artisan',
            'kyc_status' => 'actif',
            'cnmci_status' => 'non_renseigne'
        ]);

        $response = $this->actingAs($artisan, 'sanctum')->postJson("/api/v1/users/{$artisan->id}/cnmci", [
            'cnmci_number' => 'CNM-2026-999',
            'cnmci_card' => UploadedFile::fake()->image('card.jpg')
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.cnmciNumber', 'CNM-2026-999');
        $response->assertJsonPath('data.cnmciStatus', 'en_attente');

        $artisan->refresh();
        $this->assertEquals('CNM-2026-999', $artisan->cnmci_number);
        $this->assertEquals('en_attente', $artisan->cnmci_status);
        $this->assertNotNull($artisan->cnmci_card_url);
    }

    public function test_admin_can_approve_cnmci_certification()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'cnmci_number' => 'CNM-2026-999',
            'cnmci_status' => 'en_attente'
        ]);

        $response = $this->actingAs($admin)->post("/admin/kyc/{$artisan->id}/cnmci-review", [
            'decision' => 'valide'
        ]);

        $response->assertRedirect();
        $artisan->refresh();
        $this->assertEquals('valide', $artisan->cnmci_status);
    }

    public function test_admin_can_reject_cnmci_certification()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $artisan = User::factory()->create([
            'role' => 'artisan',
            'cnmci_number' => 'CNM-2026-999',
            'cnmci_status' => 'en_attente'
        ]);

        $response = $this->actingAs($admin)->post("/admin/kyc/{$artisan->id}/cnmci-review", [
            'decision' => 'rejete'
        ]);

        $response->assertRedirect();
        $artisan->refresh();
        $this->assertEquals('rejete', $artisan->cnmci_status);
    }
}
