<?php

namespace App\Services;

use App\Models\EvidenceVault;
use App\Models\JuryReview;
use App\Models\User;
use App\Support\PrivateMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service de gestion du Coffre-Fort des Preuves Numériques (Evidence Vault).
 *
 * Scelle cryptographiquement en SHA-256 toute preuve déposée (photos de diagnostic,
 * jalons de chantier, preuves de litige) pour garantir son inaltérabilité juridique.
 */
class EvidenceVaultService
{
    /**
     * Scelle une preuve dans le coffre-fort avec empreinte SHA-256.
     *
     * @param  UploadedFile|string  $file  Fichier uploadé ou chemin relatif sur le disque
     */
    public function seal(UploadedFile|string $file, User $uploader, array $context = []): EvidenceVault
    {
        $filePath = null;
        $fileUrl = $context['file_url'] ?? null;
        $sha256 = null;
        $fileSize = null;
        $mimeType = null;

        if ($file instanceof UploadedFile) {
            // Disque privé (Chantier 41) : une preuve ne vit jamais à une
            // adresse publique. L'empreinte se calcule sur le fichier reçu.
            $filePath = $context['file_path'] ?? PrivateMedia::store($file, 'vault');
            $sha256 = hash_file('sha256', $file->getRealPath());
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $fileUrl = PrivateMedia::toStored($context['file_url'] ?? null) ?? PrivateMedia::canonicalUrl($filePath);
        } elseif (is_string($file)) {
            $filePath = $file;
            $contents = $this->contents($file);

            if ($contents !== null) {
                $sha256 = hash('sha256', $contents);
                $fileSize = strlen($contents);
                $mimeType = PrivateMedia::mimeType($file) ?? (Storage::disk('public')->exists($file) ? Storage::disk('public')->mimeType($file) : null);
            } else {
                $sha256 = hash('sha256', $file);
            }
        }

        $ip = $context['ip_address'] ?? request()?->ip();
        $deviceFingerprint = $context['device_fingerprint'] ?? request()?->header('X-Device-Fingerprint');

        return EvidenceVault::create([
            'litige_id' => $context['litige_id'] ?? null,
            'mission_id' => $context['mission_id'] ?? null,
            'jalon_id' => $context['jalon_id'] ?? null,
            'evidence_type' => $context['evidence_type'] ?? 'litige',
            'uploaded_by' => $uploader->id,
            'file_url' => $fileUrl ?? $filePath ?? '',
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'sha256_hash' => $sha256 ?? hash('sha256', Str::uuid()->toString()),
            'ip_address' => $ip,
            'device_fingerprint' => $deviceFingerprint,
            'gps_lat' => $context['gps_lat'] ?? null,
            'gps_lng' => $context['gps_lng'] ?? null,
            'is_tampered' => false,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Contenu d'une preuve : sur le disque privé, à défaut sur le disque
     * public pour une preuve scellée avant le Chantier 41.
     */
    private function contents(string $target): ?string
    {
        $path = PrivateMedia::pathFromUrl($target) ?? $target;

        if (PrivateMedia::isSafePath($path) && PrivateMedia::exists($path)) {
            return PrivateMedia::contents($path);
        }

        return Storage::disk('public')->exists($target) ? Storage::disk('public')->get($target) : null;
    }

    /**
     * Vérifie l'intégrité cryptographique d'une preuve scellée.
     */
    public function verifyIntegrity(EvidenceVault $entry): bool
    {
        $target = $entry->file_path ?? $entry->file_url;

        if (! $target) {
            return false;
        }

        $contents = $this->contents($target);
        $currentHash = $contents !== null ? hash('sha256', $contents) : null;

        if ($currentHash === null && file_exists($target)) {
            $currentHash = hash_file('sha256', $target);
        }

        if ($currentHash === null || ! hash_equals($entry->sha256_hash, $currentHash)) {
            $entry->update([
                'is_tampered' => true,
                'tampered_detected_at' => now(),
            ]);

            Log::warning("[EvidenceVault] Altération détectée pour la preuve #{$entry->id} : hash attendu {$entry->sha256_hash}, calculé ".($currentHash ?? 'INTROUVABLE'));

            return false;
        }

        if ($entry->is_tampered) {
            $entry->update(['is_tampered' => false, 'tampered_detected_at' => null]);
        }

        return true;
    }

    /**
     * Audit complet de toutes les pièces scellées du coffre-fort.
     */
    public function verifyAll(): array
    {
        $entries = EvidenceVault::all();
        $total = $entries->count();
        $intact = 0;
        $tampered = 0;
        $tamperedIds = [];

        foreach ($entries as $entry) {
            if ($this->verifyIntegrity($entry)) {
                $intact++;
            } else {
                $tampered++;
                $tamperedIds[] = $entry->id;
            }
        }

        return [
            'total' => $total,
            'intact' => $intact,
            'tampered' => $tampered,
            'tampered_ids' => $tamperedIds,
        ];
    }

    /** Accès complet : parties à la mission, déposant, administrateur habilité. */
    public const ACCESS_FULL = 'complet';

    /** Accès anonymisé : juré du litige, sans identité ni position (Règle d'or 76). */
    public const ACCESS_JUROR = 'jure';

    /**
     * Niveau d'accès d'un utilisateur à une preuve, ou null s'il n'y a pas droit.
     * Les identifiants étant séquentiels, le rôle seul ne suffit jamais : il
     * faut un lien avec la mission ou le litige de la preuve.
     */
    public function accessLevel(User $user, EvidenceVault $entry): ?string
    {
        if ($user->role === 'admin') {
            return $user->can('admin.litiges.view') ? self::ACCESS_FULL : null;
        }

        if ($entry->uploaded_by === $user->id) {
            return self::ACCESS_FULL;
        }

        $mission = $entry->mission ?? $entry->litige?->mission;
        if ($mission && in_array($user->id, [$mission->client_id, $mission->artisan_id], true)) {
            return self::ACCESS_FULL;
        }

        if ($entry->litige_id && JuryReview::where('litige_id', $entry->litige_id)->where('jure_id', $user->id)->exists()) {
            return self::ACCESS_JUROR;
        }

        return null;
    }

    /**
     * Génère un certificat d'authenticité numérique pour une pièce de preuve.
     * Le téléphone du déposant n'y figure jamais ; un juré ne reçoit ni son
     * identité ni la position du dépôt.
     */
    public function generateCertificate(EvidenceVault $entry, string $access = self::ACCESS_FULL): array
    {
        $isAuthentic = ! $entry->is_tampered;
        $anonymous = $access !== self::ACCESS_FULL;

        return [
            'certificate_id' => 'CERT-VAULT-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT).'-'.substr($entry->sha256_hash, 0, 8),
            'evidence_id' => $entry->id,
            'evidence_type' => $entry->evidence_type,
            'sha256_hash' => $entry->sha256_hash,
            'file_size' => $entry->file_size,
            'mime_type' => $entry->mime_type,
            'uploaded_at' => $entry->uploaded_at?->toIso8601String(),
            'uploader' => $anonymous ? null : [
                'id' => $entry->uploader?->id,
                'name' => $entry->uploader?->name,
            ],
            'gps' => (! $anonymous && $entry->gps_lat && $entry->gps_lng) ? ['lat' => (float) $entry->gps_lat, 'lng' => (float) $entry->gps_lng] : null,
            'is_authentic' => $isAuthentic,
            'integrity_status' => [
                'is_valid' => $isAuthentic,
                'verified_at' => now()->toIso8601String(),
            ],
            'seal_verification' => $isAuthentic ? 'AUTHENTIC_AND_VERIFIED' : 'TAMPERED_WARNING',
            'tampered_detected_at' => $entry->tampered_detected_at?->toIso8601String(),
            'authority' => 'ProsArtisan Evidence Vault Authority — Côte d\'Ivoire',
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
