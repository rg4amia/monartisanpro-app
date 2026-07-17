<?php

namespace App\Services;

use App\Models\KycDocument;
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
            Storage::disk('public')->delete(str_replace('/storage/', '', $existing->file_url));
            $existing->delete();
        }

        // Stocke le fichier
        $path    = $file->store("fileshare/kyc", 'public');
        $fileUrl = Storage::disk('public')->url($path);

        $doc = KycDocument::create([
            'user_id'  => $user->id,
            'type'     => $type,
            'file_url' => $fileUrl,
            'statut'   => 'en_attente',
        ]);

        // Send a pending validation notification if it hasn't been sent yet
        $hasPendingNotif = \App\Models\Notification::where('user_id', $user->id)
            ->where('type', 'kyc')
            ->where('title', 'Compte en attente de validation')
            ->exists();

        if (!$hasPendingNotif) {
            app(\App\Services\NotificationService::class)->send(
                $user,
                'kyc',
                'Compte en attente de validation',
                'Votre compte est en attente de validation KYC. Nos équipes étudient actuellement vos pièces justificatives.'
            );
        }

        return $doc;
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
            'kyc_status'  => $user->kyc_status,
            'documents'   => [
                'cni'    => $documents->get('cni') ? [
                    'statut'   => $documents->get('cni')->statut,
                    'file_url' => $documents->get('cni')->file_url,
                ] : null,
                'selfie' => $documents->get('selfie') ? [
                    'statut'   => $documents->get('selfie')->statut,
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
