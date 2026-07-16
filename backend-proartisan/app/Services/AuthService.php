<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Trouve ou crée un utilisateur par numéro de téléphone.
     */
    public function findOrCreateByPhone(string $phone): User
    {
        return User::firstOrCreate(
            ['phone' => $phone],
            ['kyc_status' => 'en_attente']
        );
    }

    /**
     * Met à jour le rôle et le nom de l'utilisateur après inscription.
     */
    public function register(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'role' => $data['role'],
        ]);

        if ($data['role'] === 'artisan') {
            $artisanData = [];
            if (isset($data['sector_id'])) $artisanData['sector_id'] = $data['sector_id'];
            if (isset($data['trade_id'])) $artisanData['trade_id'] = $data['trade_id'];
            if (isset($data['bio'])) $artisanData['bio'] = $data['bio'];
            if (isset($data['experience_years'])) $artisanData['experience_years'] = $data['experience_years'];

            \App\Models\ArtisanProfile::updateOrCreate(
                ['user_id' => $user->id],
                $artisanData
            );
        }

        return $user->fresh();
    }

    /**
     * Crée un token Sanctum pour l'utilisateur.
     */
    public function createToken(User $user, ?string $deviceFingerprint = null): string
    {
        if (! $user->isAccountActive()) {
            throw ValidationException::withMessages([
                'account' => [$user->account_status === 'banni'
                    ? 'Votre compte a ete banni suite a des litiges repetes.'
                    : 'Votre compte est temporairement bloque en raison de litiges abusifs.'],
            ]);
        }

        if ($user->isArtisan() && $deviceFingerprint) {
            if ($user->device_fingerprint === null) {
                $user->update(['device_fingerprint' => $deviceFingerprint]);
            } elseif ($user->device_fingerprint !== $deviceFingerprint) {
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'title'   => 'Alerte sécurité : Changement d\'appareil suspect',
                    'body'    => 'Un changement suspect d\'appareil (IMEI) a été détecté. Votre Score N\'Zassa est gelé par mesure de sécurité.',
                    'type'    => 'security_alert',
                ]);

                $user->update([
                    'score_frozen' => true,
                    'device_fingerprint' => $deviceFingerprint,
                ]);
            }
        }

        return $user->createToken('prosartisan-mobile')->plainTextToken;
    }

    /**
     * Révoque tous les tokens de l'utilisateur.
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }
}
