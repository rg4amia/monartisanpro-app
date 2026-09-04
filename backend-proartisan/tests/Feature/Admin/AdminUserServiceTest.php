<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chantier C1 — logique métier de gestion des comptes déplacée hors du controller.
 */
class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminUserService::class);
    }

    public function test_toggle_score_freeze_flips_state_for_an_artisan(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'score_frozen' => false]);

        $this->assertTrue($this->service->toggleScoreFreeze($artisan));
        $this->assertTrue($artisan->fresh()->score_frozen);

        $this->assertFalse($this->service->toggleScoreFreeze($artisan->fresh()));
        $this->assertFalse($artisan->fresh()->score_frozen);
    }

    public function test_toggle_score_freeze_rejected_for_non_artisan(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->expectException(\LogicException::class);
        $this->service->toggleScoreFreeze($client);
    }

    public function test_review_cnmci_rejected_for_non_artisan(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->expectException(\LogicException::class);
        $this->service->reviewCnmci($client, 'valide');
    }

    public function test_review_cnmci_updates_status_for_an_artisan(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'cnmci_status' => 'en_attente']);

        $this->service->reviewCnmci($artisan, 'valide');

        $this->assertSame('valide', $artisan->fresh()->cnmci_status);
    }

    public function test_password_is_hashed_on_create(): void
    {
        $user = $this->service->create([
            'name' => 'Test Hash',
            'phone' => '+2250199887766',
            'role' => 'client',
            'password' => 'secret123',
            'kyc_status' => 'en_attente',
            'account_status' => 'actif',
        ]);

        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(password_verify('secret123', $user->password));
    }
}
