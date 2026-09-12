<?php

namespace Tests\Feature;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les pièces KYC sont des pièces d'identité. Elles étaient écrites sur le
 * disque public, donc joignables par une URL permanente et sans
 * authentification : toute fuite de lien exposait une CNI définitivement.
 */
class KycDocumentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, string $type = 'cni'): KycDocument
    {
        return app(KycService::class)->uploadDocument(
            $user,
            $type,
            UploadedFile::fake()->image('cni.jpg')
        );
    }

    public function test_an_uploaded_document_never_lands_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = $this->upload($user);

        // Le cœur du correctif : le fichier est sur le disque privé…
        $this->assertTrue(Storage::disk('local')->exists($document->storagePath()));

        // …et le disque public ne contient plus rien.
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_stored_reference_is_a_private_path_not_a_public_url(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = $this->upload($user);

        $raw = $document->storagePath();

        $this->assertStringStartsWith('kyc/', $raw);
        $this->assertStringNotContainsString('/storage/', $raw);
        $this->assertFalse($document->isLegacy());
    }

    public function test_the_exposed_url_is_signed_and_temporary(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = $this->upload($user);

        $url = $document->file_url;

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }

    public function test_the_file_cannot_be_fetched_without_a_valid_signature(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = $this->upload($user);

        $this->get("/kyc/documents/{$document->id}/file")->assertForbidden();

        $this->get("/kyc/documents/{$document->id}/file?signature=faux&expires=9999999999")
            ->assertForbidden();
    }

    public function test_a_valid_signed_url_serves_the_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = $this->upload($user);

        // Contre-épreuve : le durcissement ne doit pas casser la consultation
        // légitime depuis le backoffice ou l'espace du titulaire.
        $this->get($document->file_url)->assertOk();
    }

    public function test_an_expired_signature_is_refused(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = $this->upload($user);
        $url = $document->file_url;

        $this->travel(16)->minutes();

        $this->get($url)->assertForbidden();
    }

    public function test_replacing_a_document_removes_the_previous_private_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $first = $this->upload($user);
        $firstPath = $first->storagePath();

        $this->upload($user);

        $this->assertFalse(
            Storage::disk('local')->exists($firstPath),
            "L'ancienne pièce doit être effacée : une CNI périmée ne doit pas survivre en clair."
        );
    }

    public function test_legacy_public_documents_remain_readable(): void
    {
        // Les dossiers déjà traités portent une URL absolue : on ne doit ni la
        // casser, ni tenter de la servir depuis le disque privé.
        $user = User::factory()->create(['role' => 'artisan']);

        $legacy = KycDocument::create([
            'user_id' => $user->id,
            'type' => 'cni',
            'file_url' => 'https://prosartisan.net/storage/fileshare/kyc/ancienne.jpg',
            'statut' => 'approuve',
        ]);

        $this->assertTrue($legacy->isLegacy());
        $this->assertSame(
            'https://prosartisan.net/storage/fileshare/kyc/ancienne.jpg',
            $legacy->file_url
        );
    }
}
