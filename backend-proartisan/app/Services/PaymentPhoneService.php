<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Numéro de paiement Mobile Money d'un compte (Chantier 27, lot E).
 *
 * C'est vers ce numéro que partent les gains d'un artisan, d'un fournisseur
 * ou d'un livreur. Il se changeait avec le seul jeton de session : qui tenait
 * une session détournait les versements suivants. Le changement exige
 * désormais un code envoyé au numéro du compte, prévient le titulaire et
 * suspend les retraits pendant un délai.
 */
class PaymentPhoneService
{
    /** Rôles qui reçoivent des gains sur leur numéro de paiement. */
    public const EARNER_ROLES = ['artisan', 'fournisseur', 'livreur'];

    /** Action du code de confirmation, distincte du code de connexion. */
    public const OTP_ACTION = 'payment_phone';

    public const OTP_REQUIRED_MESSAGE = 'Un code de confirmation, envoyé par SMS au numéro de votre compte, est nécessaire pour changer de numéro de paiement.';

    public function __construct(
        private OtpService $otp,
        private NotificationService $notifications,
    ) {}

    public function lockHours(): int
    {
        return max(0, (int) config('prosartisan.payment_phone.withdrawal_lock_hours', 24));
    }

    /** Le compte reçoit-il des gains, donc un numéro de paiement protégé ? */
    public function isProtected(User $user): bool
    {
        return in_array($user->role, self::EARNER_ROLES, true);
    }

    /** Numéro ramené à la forme `+225` suivie de dix chiffres, pour comparer. */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '+') ? $digits : '+225'.$digits;
    }

    /**
     * Ce numéro changerait-il la destination des versements du compte ?
     * Revenir au numéro du compte, ou garder le même numéro, n'exige rien.
     */
    public function changesDestination(User $user, ?string $phone): bool
    {
        $new = self::normalize($phone);

        if ($new === null) {
            return false;
        }

        return $new !== self::normalize($user->payment_phone) && $new !== self::normalize($user->phone);
    }

    /** Envoie le code de confirmation au numéro du compte, jamais au nouveau numéro. */
    public function sendConfirmationCode(User $user): void
    {
        $this->otp->sendOtp((string) $user->phone, self::OTP_ACTION);
    }

    /**
     * Applique le changement demandé depuis le profil.
     *
     * @throws ValidationException Code absent ou invalide pour un compte protégé.
     */
    public function update(User $user, ?string $phone, ?string $provider, ?string $code): void
    {
        $changes = $this->changesDestination($user, $phone);

        if ($changes && $this->isProtected($user)) {
            if ($code === null || $code === '') {
                throw ValidationException::withMessages(['payment_phone_code' => [self::OTP_REQUIRED_MESSAGE]]);
            }

            if (! $this->otp->verifyOtp((string) $user->phone, $code, self::OTP_ACTION)) {
                throw ValidationException::withMessages(['payment_phone_code' => ['Code de confirmation invalide ou expiré.']]);
            }
        }

        $this->apply($user, $phone, $provider, $changes);
    }

    /**
     * Numéro fourni au fil d'un autre parcours (devis, bon matériel, course,
     * mission). Il renseigne un compte qui n'en a pas ; il ne remplace jamais
     * le numéro d'un compte qui reçoit des gains : ce changement passe par le
     * profil, avec son code de confirmation.
     */
    public function syncFromFlow(User $user, ?string $phone, ?string $provider): void
    {
        if ($phone === null || trim($phone) === '') {
            return;
        }

        $changes = $this->changesDestination($user, $phone);

        if ($changes && $this->isProtected($user) && filled($user->payment_phone)) {
            Log::info('[Numéro de paiement] Changement ignoré hors du profil.', ['user_id' => $user->id]);

            return;
        }

        $this->apply($user, $phone, $provider, $changes);
    }

    /** Fin du blocage des retraits et versements, ou null s'il n'y en a pas. */
    public function withdrawalsLockedUntil(User $user): ?CarbonInterface
    {
        if (! $this->isProtected($user) || $user->payment_phone_changed_at === null || $this->lockHours() === 0) {
            return null;
        }

        $until = $user->payment_phone_changed_at->copy()->addHours($this->lockHours());

        return $until->isFuture() ? $until : null;
    }

    /**
     * @throws \InvalidArgumentException Retraits suspendus, message en français.
     */
    public function assertWithdrawalsOpen(User $user): void
    {
        $until = $this->withdrawalsLockedUntil($user);

        if ($until !== null) {
            throw new \InvalidArgumentException($this->lockMessage($until));
        }
    }

    /**
     * Un retrait Mobile Money part vers le numéro de paiement enregistré, ou
     * vers le numéro du compte : saisir un autre numéro à la demande de
     * retrait contournerait le code de confirmation.
     *
     * @throws \InvalidArgumentException
     */
    public function assertRegisteredDestination(User $user, ?string $phone): void
    {
        if ($this->isProtected($user) && $this->changesDestination($user, $phone)) {
            throw new \InvalidArgumentException('Le retrait part vers votre numéro de paiement enregistré. Pour retirer vers un autre numéro, modifiez-le d\'abord dans votre profil.');
        }
    }

    public function lockMessage(CarbonInterface $until): string
    {
        return 'Votre numéro de paiement a été modifié récemment : par sécurité, les retraits et versements reprennent le '
            .$until->format('d/m/Y').' à '.$until->format('H:i').'.';
    }

    private function apply(User $user, ?string $phone, ?string $provider, bool $changes): void
    {
        $data = ['payment_phone' => $phone];

        if ($provider !== null) {
            $data['preferred_payment_provider'] = $provider;
        }

        if ($changes && $this->isProtected($user)) {
            $data['payment_phone_changed_at'] = now();
        }

        $user->update($data);

        if (! $changes || ! $this->isProtected($user)) {
            return;
        }

        try {
            $this->notifications->notify($user, 'compte.numero_paiement_modifie.utilisateur', [
                'numero' => substr((string) self::normalize($phone), -4),
                'heures' => $this->lockHours(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Numéro de paiement] Notification non envoyée : '.$e->getMessage());
        }
    }
}
