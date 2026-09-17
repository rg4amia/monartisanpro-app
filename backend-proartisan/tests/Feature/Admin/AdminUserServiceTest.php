<?php

namespace Tests\Feature\Admin;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_update_stores_a_new_photo_and_deletes_the_previous_one(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'client']);
        $oldPath = UploadedFile::fake()->image('old.jpg')->store('avatars', 'local');
        $user->update(['photo_path' => $oldPath]);

        $this->service->update($user, $this->baseUpdateData($user, [
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ]));

        $user->refresh();
        $this->assertNotNull($user->photo_path);
        $this->assertNotSame($oldPath, $user->photo_path);
        Storage::disk('local')->assertExists($user->photo_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_update_uploads_kyc_documents_and_resets_status_to_pending(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'client']);
        KycDocument::create([
            'user_id' => $user->id,
            'type' => 'cni',
            'file_url' => 'kyc/old-cni.jpg',
            'statut' => 'approuve',
        ]);

        $this->service->update($user, $this->baseUpdateData($user, [
            'documents' => ['cni' => UploadedFile::fake()->image('cni.jpg'), 'selfie' => null],
        ]));

        $document = KycDocument::where('user_id', $user->id)->where('type', 'cni')->sole();
        $this->assertSame('en_attente', $document->statut);
        Storage::disk('local')->assertExists($document->getRawOriginal('file_url'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseUpdateData(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'kyc_status' => $user->kyc_status,
            'account_status' => 'actif',
        ], $overrides);
    }
}
