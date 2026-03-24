<?php

namespace App\Services;

use App\Models\Jalon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class JalonService
{
    public function __construct(
        private OtpService $otpService,
        private WalletService $walletService,
        private NotificationService $notificationService,
    ) {}

    /**
     * L'artisan soumet un jalon terminé avec photos géolocalisées.
     */
    public function submit(Jalon $jalon, array $photos = []): void
    {
        $jalon->update([
            'statut'      => 'soumis',
            'photos_json' => $photos,
        ]);

        if ($jalon->mission->status === 'financee') {
            $jalon->mission->update(['status' => 'en_cours']);
        }

        // Notifier le client
        $this->notificationService->send(
            $jalon->mission->client,
            'validation',
            'Jalon à valider',
            "L'artisan a soumis le jalon #{$jalon->ordre}. Validez avec le code OTP.",
            ['mission_id' => $jalon->mission_id, 'jalon_id' => $jalon->id]
        );
    }

    /**
     * Envoie l'OTP de validation au client par SMS.
     */
    public function requestOtp(Jalon $jalon): void
    {
        if ($jalon->mission->isFundsFrozen()) {
            throw ValidationException::withMessages([
                'jalon' => ['Les fonds de cette mission sont geles en raison d un litige.'],
            ]);
        }

        $client  = $jalon->mission->client;
        $otp     = $this->otpService->sendOtp($client->phone);
        $expires = now()->addMinutes(config('prosartisan.otp.ttl', 5));

        $jalon->update([
            'otp_code'       => $otp,
            'otp_expires_at' => $expires,
        ]);
    }

    /**
     * Valide l'OTP du client et libère les fonds du jalon.
     * RÈGLE CRITIQUE : libération impossible sans OTP valide.
     */
    public function validateOtp(Jalon $jalon, string $otp): bool
    {
        $client = $jalon->mission->client;

        if ($jalon->mission->isFundsFrozen()) {
            throw ValidationException::withMessages([
                'jalon' => ['Les fonds de cette mission sont geles en raison d un litige.'],
            ]);
        }

        if (! $this->otpService->verifyOtp($client->phone, $otp)) {
            return false;
        }

        if (! $jalon->isOtpValid($otp)) {
            return false;
        }

        $jalon->update(['statut' => 'valide', 'valide_at' => now(), 'otp_code' => null]);

        // RÈGLE : missions > 2M FCFA → validation physique Référent requise
        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        if ($jalon->mission->montant_total > $seuil) {
            $jalon->mission->update(['referent_required' => true]);
            Log::info("[Référent requis] Mission #{$jalon->mission_id} > {$seuil} FCFA");
            // En production : notifier le référent de zone
            return true;
        }

        // Libération immédiate des fonds
        $this->walletService->releaseJalon($jalon);

        $this->notificationService->send(
            $jalon->mission->artisan,
            'payment',
            'Paiement reçu !',
            "Le jalon #{$jalon->ordre} a été validé. Paiement en cours.",
            ['mission_id' => $jalon->mission_id]
        );

        return true;
    }
}
