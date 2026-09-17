<?php

namespace Tests\Feature\Admin;

use App\Models\FournisseurAgree;
use App\Models\KycDocument;
use App\Models\Sector;
use App\Models\Trade;
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

    public function test_update_assigns_a_sector_to_a_fournisseur(): void
    {
        $sector = Sector::create(['name' => 'Électricité']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur']);
        FournisseurAgree::create(['user_id' => $fournisseur->id, 'nom_boutique' => 'Quincaillerie Test', 'statut' => 'agree']);

        $this->service->update($fournisseur, $this->baseUpdateData($fournisseur, [
            'fournisseur_sector_id' => $sector->id,
        ]));

        $this->assertSame($sector->id, $fournisseur->fournisseurAgree->fresh()->sector_id);
    }

    public function test_update_assigns_a_trade_matching_the_selected_sector(): void
    {
        $sector = Sector::create(['name' => 'Électricité']);
        $trade = Trade::create(['sector_id' => $sector->id, 'name' => 'Électricien bâtiment']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur']);
        FournisseurAgree::create(['user_id' => $fournisseur->id, 'nom_boutique' => 'Quincaillerie Test', 'statut' => 'agree']);

        $this->service->update($fournisseur, $this->baseUpdateData($fournisseur, [
            'fournisseur_sector_id' => $sector->id,
            'fournisseur_trade_id' => $trade->id,
        ]));

        $this->assertSame($trade->id, $fournisseur->fournisseurAgree->fresh()->trade_id);
    }

    public function test_update_discards_a_trade_that_does_not_belong_to_the_selected_sector(): void
    {
        $sectorA = Sector::create(['name' => 'Électricité']);
        $sectorB = Sector::create(['name' => 'Plomberie']);
        $tradeFromSectorB = Trade::create(['sector_id' => $sectorB->id, 'name' => 'Plombier sanitaire']);
        $fournisseur = User::factory()->create(['role' => 'fournisseur']);
        FournisseurAgree::create(['user_id' => $fournisseur->id, 'nom_boutique' => 'Quincaillerie Test', 'statut' => 'agree']);

        $this->service->update($fournisseur, $this->baseUpdateData($fournisseur, [
            'fournisseur_sector_id' => $sectorA->id,
            'fournisseur_trade_id' => $tradeFromSectorB->id,
        ]));

        $this->assertNull($fournisseur->fournisseurAgree->fresh()->trade_id);
    }

    public function test_update_ignores_fournisseur_sector_for_a_non_fournisseur_role(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $sector = Sector::create(['name' => 'Électricité']);

        // Ne doit pas lever d'erreur : un client n'a pas de fournisseurAgree.
        $this->service->update($client, $this->baseUpdateData($client, [
            'fournisseur_sector_id' => $sector->id,
        ]));

        $this->assertNull($client->fournisseurAgree);
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
