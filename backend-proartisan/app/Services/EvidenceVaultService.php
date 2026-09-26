<?php

namespace App\Services;

use App\Models\EvidenceVault;
use App\Models\User;
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
     * @param UploadedFile|string $file Fichier uploadé ou chemin relatif sur le disque
     */
    public function seal(UploadedFile|string $file, User $uploader, array $context = []): EvidenceVault
    {
        $filePath = null;
        $fileUrl = $context['file_url'] ?? null;
        $sha256 = null;
        $fileSize = null;
        $mimeType = null;

        if ($file instanceof UploadedFile) {
            $disk = Storage::disk('public');
            $filePath = $context['file_path'] ?? $file->store('vault', 'public');
            $sha256 = hash('sha256', $disk->get($filePath));
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $fileUrl = $context['file_url'] ?? $disk->url($filePath);
        } elseif (is_string($file)) {
            $filePath = $file;
            $disk = Storage::disk('public');

            if ($disk->exists($file)) {
                $sha256 = hash('sha256', $disk->get($file));
                $fileSize = $disk->size($file);
                $mimeType = $disk->mimeType($file);
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
     * Vérifie l'intégrité cryptographique d'une preuve scellée.
     */
    public function verifyIntegrity(EvidenceVault $entry): bool
    {
        $disk = Storage::disk('public');
        $target = $entry->file_path ?? $entry->file_url;

        if (! $target) {
            return false;
        }

        $currentHash = null;

        if ($disk->exists($target)) {
            $currentHash = hash('sha256', $disk->get($target));
        } elseif (file_exists($target)) {
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

    /**
     * Génère un certificat d'authenticité numérique pour une pièce de preuve.
     */
    public function generateCertificate(EvidenceVault $entry): array
    {
        $isAuthentic = ! $entry->is_tampered;

        return [
            'certificate_id' => 'CERT-VAULT-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT).'-'.substr($entry->sha256_hash, 0, 8),
            'evidence_id' => $entry->id,
            'evidence_type' => $entry->evidence_type,
            'sha256_hash' => $entry->sha256_hash,
            'file_size' => $entry->file_size,
            'mime_type' => $entry->mime_type,
            'uploaded_at' => $entry->uploaded_at?->toIso8601String(),
            'uploader' => [
                'id' => $entry->uploader?->id,
                'name' => $entry->uploader?->name,
                'phone' => $entry->uploader?->phone,
            ],
            'gps' => ($entry->gps_lat && $entry->gps_lng) ? ['lat' => (float) $entry->gps_lat, 'lng' => (float) $entry->gps_lng] : null,
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
