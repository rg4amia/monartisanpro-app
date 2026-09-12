<?php

namespace Tests\Feature;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La commande déplace des pièces d'identité et supprime l'original : une
 * erreur y est irréversible. Chaque cas de bord est donc couvert, y compris
 * les échecs, où l'ancienne référence doit survivre intacte.
 */
class MigrateKycToPrivateDiskTest extends TestCase
{
    use RefreshDatabase;

    private function legacyDocument(string $filename = 'cni.jpg', bool $withFile = true): KycDocument
    {
        $user = User::factory()->create(['role' => 'artisan']);

        if ($withFile) {
            Storage::disk('public')->putFileAs(
                'fileshare/kyc',
                UploadedFile::fake()->image($filename),
                $filename
            );
        }

        return KycDocument::create([
            'user_id' => $user->id,
            'type' => 'cni',
            'file_url' => "https://prosartisan.net/storage/fileshare/kyc/{$filename}",
            'statut' => 'approuve',
        ]);
    }

    public function test_a_legacy_document_is_moved_to_the_private_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $document = $this->legacyDocument();

        $this->artisan('kyc:migrate-to-private')->assertSuccessful();

        $document->refresh();

        // Référence réécrite vers un chemin privé…
        $this->assertSame('kyc/cni.jpg', $document->storagePath());
        $this->assertFalse($document->isLegacy());

        // …fichier présent côté privé…
        $this->assertTrue(Storage::disk('local')->exists('kyc/cni.jpg'));

        // …et surtout, plus rien d'exposé publiquement.
        $this->assertFalse(Storage::disk('public')->exists('fileshare/kyc/cni.jpg'));
    }

    public function test_the_migrated_document_is_served_through_a_signed_url(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $document = $this->legacyDocument();

        $this->artisan('kyc:migrate-to-private')->assertSuccessful();

        $url = $document->refresh()->file_url;

        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertOk();
    }

    public function test_dry_run_changes_nothing(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $document = $this->legacyDocument();
        $before = $document->storagePath();

        $this->artisan('kyc:migrate-to-private', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $document->refresh()->storagePath());
        $this->assertTrue(Storage::disk('public')->exists('fileshare/kyc/cni.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('kyc/cni.jpg'));
    }

    public function test_keep_source_preserves_the_public_copy(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->legacyDocument();

        $this->artisan('kyc:migrate-to-private', ['--keep-source' => true])->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('kyc/cni.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('fileshare/kyc/cni.jpg'));
    }

    public function test_a_missing_file_leaves_the_reference_untouched(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // Ligne en base dont le fichier a disparu : réécrire la référence
        // créerait un lien privé pointant dans le vide.
        $document = $this->legacyDocument('perdue.jpg', withFile: false);

        $this->artisan('kyc:migrate-to-private')->assertSuccessful();

        $this->assertTrue($document->refresh()->isLegacy());
        $this->assertStringContainsString('perdue.jpg', (string) $document->storagePath());
    }

    public function test_already_private_documents_are_left_alone(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        $document = KycDocument::create([
            'user_id' => $user->id,
            'type' => 'selfie',
            'file_url' => 'kyc/deja-privee.jpg',
            'statut' => 'approuve',
        ]);

        $this->artisan('kyc:migrate-to-private')
            ->expectsOutputToContain('Aucune pièce KYC sur le disque public')
            ->assertSuccessful();

        $this->assertSame('kyc/deja-privee.jpg', $document->refresh()->storagePath());
    }

    public function test_the_command_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->legacyDocument();

        $this->artisan('kyc:migrate-to-private')->assertSuccessful();
        $this->artisan('kyc:migrate-to-private')->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('kyc/cni.jpg'));
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_a_reference_escaping_the_storage_root_is_refused(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'artisan']);
        KycDocument::create([
            'user_id' => $user->id,
            'type' => 'cni',
            'file_url' => 'https://prosartisan.net/storage/../../.env',
            'statut' => 'approuve',
        ]);

        // Échec compté, mais aucune lecture hors du dossier de stockage.
        $this->artisan('kyc:migrate-to-private')->assertFailed();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
