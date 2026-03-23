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

        return $user->fresh();
    }

    /**
     * Crée un token Sanctum pour l'utilisateur.
     */
    public function createToken(User $user): string
    {
        if (! $user->isAccountActive()) {
            throw ValidationException::withMessages([
                'account' => [$user->account_status === 'banni'
                    ? 'Votre compte a ete banni suite a des litiges repetes.'
                    : 'Votre compte est temporairement bloque en raison de litiges abusifs.'],
            ]);
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
