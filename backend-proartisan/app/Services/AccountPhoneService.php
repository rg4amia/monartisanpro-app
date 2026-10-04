<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Changement du numéro du compte par son titulaire connecté.
 *
 * Le nouveau numéro est prouvé par un code qui lui est envoyé. Une fois le
 * numéro changé, les autres sessions du compte sont fermées et l'ancien
 * numéro est prévenu : un téléphone volé encore connecté ne garde pas l'accès,
 * et le titulaire apprend un changement qu'il n'a pas fait.
 */
class AccountPhoneService
{
    public const OTP_ACTION = 'change_phone';

    public const TAKEN_MESSAGE = 'Ce numéro de téléphone est déjà associé à un autre compte.';

    public const INVALID_CODE_MESSAGE = 'Code OTP invalide ou expiré.';

    public function __construct(
        private OtpService $otp,
        private NotificationService $notifications,
    ) {}

    /**
     * Envoie le code de confirmation au nouveau numéro.
     */
    public function sendCode(User $user, string $newPhone): void
    {
        $this->assertAvailable($user, $newPhone);

        $this->otp->sendOtp($newPhone, self::OTP_ACTION);
    }

    /**
     * Change le numéro après vérification du code.
     *
     * @param  int|null  $currentTokenId  Jeton de la session qui fait la demande, seul conservé.
     */
    public function change(User $user, string $newPhone, string $code, ?int $currentTokenId): User
    {
        if (! $this->otp->verifyOtp($newPhone, $code, self::OTP_ACTION)) {
            throw ValidationException::withMessages(['otp' => self::INVALID_CODE_MESSAGE]);
        }

        $this->assertAvailable($user, $newPhone);

        $previousPhone = $user->phone;
        $user->update(['phone' => $newPhone]);

        $user->tokens()
            ->when($currentTokenId !== null, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        if ($previousPhone !== $newPhone) {
            $this->warnPreviousPhone($user, $previousPhone);
        }

        return $user;
    }

    /**
     * Un numéro porté par un autre compte, même supprimé, n'est pas libre :
     * la colonne est unique, comptes supprimés compris.
     */
    private function assertAvailable(User $user, string $newPhone): void
    {
        $taken = User::withTrashed()
            ->where('phone', $newPhone)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['new_phone' => self::TAKEN_MESSAGE]);
        }
    }

    private function warnPreviousPhone(User $user, ?string $previousPhone): void
    {
        try {
            // Le SMS part vers l'ancien numéro : c'est lui qu'il faut prévenir.
            $this->notifications->notify(
                $user,
                'compte.telephone_change.utilisateur',
                ['telephone' => (string) $user->phone],
                [],
                $previousPhone,
            );
        } catch (\Throwable $e) {
            Log::warning('[Comptes] Notification de changement de numéro non envoyée : '.$e->getMessage());
        }
    }
}
