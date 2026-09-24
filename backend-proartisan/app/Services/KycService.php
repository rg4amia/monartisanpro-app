<?php

namespace App\Services;

use App\Models\KycDocument;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class KycService
{
    /**
     * Upload un document KYC et crée l'enregistrement en base.
     */
    public function uploadDocument(User $user, string $type, UploadedFile $file): KycDocument
    {
        // Supprime l'ancien document du même type s'il existe
        $existing = KycDocument::where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $this->deleteStoredFile($existing);
            $existing->delete();
        }

        // Disque privé : une CNI ou un selfie ne doit pas être servi par une
        // URL publique permanente. La consultation passe par une URL signée à
        // durée limitée (cf. KycDocument::fileUrl et la route kyc.document.file).
        $path = $file->store('kyc', 'local');

        $doc = KycDocument::create([
            'user_id' => $user->id,
            'type' => $type,
            'file_url' => $path,
            'statut' => 'en_attente',
        ]);

        // Send a pending validation notification if it hasn't been sent yet
        $hasPendingNotif = Notification::where('user_id', $user->id)
            ->where('type', 'kyc')
            ->where('title', 'Compte en attente de validation')
            ->exists();

        if (! $hasPendingNotif) {
            app(NotificationService::class)->send(
                $user,
                'kyc',
                'Compte en attente de validation',
                'Votre compte est en attente de validation KYC. Nos équipes étudient actuellement vos pièces justificatives.'
            );
        }

        return $doc;
    }

    /**
     * Supprime le fichier physique d'un document, sur le disque où il se trouve
     * réellement : les pièces d'avant la migration vivent encore sur le disque
     * public, les nouvelles sur le disque privé.
     */
    private function deleteStoredFile(KycDocument $document): void
    {
        $raw = $document->storagePath();

        if ($raw === null) {
            return;
        }

        if ($document->isLegacy()) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $raw));

            return;
        }

        Storage::disk('local')->delete($raw);
    }

    /**
     * Retourne le statut KYC de l'utilisateur.
     */
    public function getStatus(User $user): array
    {
        $documents = KycDocument::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->keyBy('type');

        return [
            'kyc_status' => $user->kyc_status,
            'documents' => [
                'cni' => $documents->get('cni') ? [
                    'statut' => $documents->get('cni')->statut,
                    'file_url' => $documents->get('cni')->file_url,
                ] : null,
                'selfie' => $documents->get('selfie') ? [
                    'statut' => $documents->get('selfie')->statut,
                    'file_url' => $documents->get('selfie')->file_url,
                ] : null,
            ],
            'can_transact' => $user->kyc_status === 'actif',
        ];
    }

    /**
     * Vérifie si l'utilisateur a les 2 documents approuvés.
     */
    public function isKycComplete(User $user): bool
    {
        return KycDocument::where('user_id', $user->id)
            ->where('statut', 'approuve')
            ->whereIn('type', ['cni', 'selfie'])
            ->count() === 2;
    }
}
