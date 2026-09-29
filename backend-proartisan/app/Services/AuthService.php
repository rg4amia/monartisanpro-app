<?php

namespace App\Services;

use App\Models\ArtisanProfile;
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
            'cgu_accepted_at' => (isset($data['cgu_accepted']) && $data['cgu_accepted']) ? now() : ($user->cgu_accepted_at ?? now()),
        ]);

        if ($data['role'] === 'artisan') {
            $artisanData = [];
            if (isset($data['sector_id'])) {
                $artisanData['sector_id'] = $data['sector_id'];
            }
            if (isset($data['trade_id'])) {
                $artisanData['trade_id'] = $data['trade_id'];
            }
            if (isset($data['bio'])) {
                $artisanData['bio'] = $data['bio'];
            }
            if (isset($data['experience_years'])) {
                $artisanData['experience_years'] = $data['experience_years'];
            }

            ArtisanProfile::updateOrCreate(
                ['user_id' => $user->id],
                $artisanData
            );
        }

        $user = $user->fresh();

        if ($user->kyc_status === 'en_attente') {
            $notifications = app(NotificationService::class);
            $hasPendingNotif = $notifications->alreadyNotified(
                $user,
                ['kyc.documents_attendus.utilisateur', 'kyc.en_examen.utilisateur']
            );

            if (! $hasPendingNotif) {
                $notifications->notify($user, 'kyc.documents_attendus.utilisateur');

                // Notification pour les administrateurs
                $notifications->notifyAdmins(
                    'kyc.nouveau_profil.admin',
                    ['nom' => $user->name, 'role' => $user->role],
                    ['user_id' => $user->id]
                );
            }
        }

        // Lie automatiquement les invitations de parrainage en attente pour
        // ce numéro de téléphone, maintenant que le rôle définitif est
        // connu. No-op silencieux si aucune invitation ne correspond.
        app(ParrainageService::class)->linkPending($user);
        app(ParrainageClientService::class)->linkPending($user);

        return $user;
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

        if ($deviceFingerprint && ($user->account_status ?? 'actif') === 'actif') {
            $bannedOnSameDevice = User::where('device_fingerprint', $deviceFingerprint)
                ->where('id', '!=', $user->id)
                ->where('account_status', 'banni')
                ->exists();

            if ($bannedOnSameDevice) {
                $user->update([
                    'account_status' => 'banni',
                    'account_status_reason' => 'Réinscription détectée depuis un appareil déjà associé à un compte banni.',
                    'blocked_at' => now(),
                ]);

                try {
                    app(NotificationService::class)->notifyAdmins(
                        'securite.contournement_bannissement.admin',
                        ['compte' => $user->id, 'telephone' => $user->phone],
                        ['user_id' => $user->id]
                    );
                } catch (\Throwable $e) {
                    // Best-effort : l'échec de la notification ne doit jamais bloquer le blocage du compte.
                }

                throw ValidationException::withMessages([
                    'account' => ['Votre compte a été bloqué : cet appareil est associé à un compte précédemment banni.'],
                ]);
            }
        }

        if ($user->isArtisan() && $deviceFingerprint) {
            if ($user->device_fingerprint === null) {
                $user->update(['device_fingerprint' => $deviceFingerprint]);
            } elseif ($user->device_fingerprint !== $deviceFingerprint) {
                // Push et SMS atteignent aussi l'ancien appareil : le titulaire
                // est prévenu si quelqu'un d'autre se connecte à son compte.
                app(NotificationService::class)->notify($user, 'securite.changement_appareil.artisan');

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
