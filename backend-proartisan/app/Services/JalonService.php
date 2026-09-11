<?php

namespace App\Services;

use App\Models\Jalon;
use App\States\Mission\DisputedState;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class JalonService
{
    public function __construct(
        private OtpService $otpService,
        private WalletService $walletService,
        private NotificationService $notificationService,
        private ?FraudDetectionService $fraudService = null,
        private ?GeminiService $geminiService = null,
    ) {
        $this->fraudService = $fraudService ?? app(FraudDetectionService::class);
        $this->geminiService = $geminiService ?? app(GeminiService::class);
    }

    public function submit(Jalon $jalon, array $photos = []): void
    {
        if (!empty($photos)) {
            $jalon->update(['photos_json' => $photos]);
        }

        $analysis = $this->analyzePhotos($jalon, $photos);

        // Sauvegarde de l'analyse visuelle multimodale et du score sur le jalon
        $jalon->update([
            'conformity_score'     => $analysis['conformity_score'] ?? null,
            'vision_analysis_json' => $analysis,
        ]);

        if (!$analysis['approved'] || ($analysis['conformity_score'] !== null && $analysis['conformity_score'] < 40)) {
            // Signalement automatique au centre anti-fraude
            $this->fraudService->recordVisionAnomaly($jalon, $analysis);

            $reason = $analysis['summary'] ?? $analysis['reason'] ?? 'Preuve visuelle jugée non conforme aux travaux spécifiés.';
            throw ValidationException::withMessages([
                'photos' => [$reason],
            ]);
        }

        $this->fraudService->analyzeMilestoneSubmission($jalon, $photos);

        $jalon->update([
            'statut'      => 'soumis',
        ]);

        if ($jalon->mission->status instanceof \App\States\Mission\FundedLockedState) {
            $jalon->mission->status->transitionTo(\App\States\Mission\InProgressState::class);
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

    public function analyzePhotos(Jalon $jalon, array $photos = []): array
    {
        return $this->geminiService->analyzeMilestoneVision($jalon, $photos);
    }

    /**
     * Envoie l'OTP de validation au client par SMS ou WhatsApp.
     */
    public function requestOtp(Jalon $jalon, ?string $channel = null): void
    {
        if ($jalon->mission->isFundsFrozen()) {
            throw ValidationException::withMessages([
                'jalon' => ['Les fonds de cette mission sont geles en raison d un litige.'],
            ]);
        }

        $client  = $jalon->mission->client;
        $otp     = $this->otpService->sendOtp($client->phone, null, $channel);
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

        $submittedAt = $jalon->updated_at;
        $jalon->update(['statut' => 'valide', 'valide_at' => now(), 'otp_code' => null]);

        // Détection de fraude / collusion (ex: validation ultra-rapide < 120s)
        $fraudAlert = $this->fraudService->analyzeMilestoneValidation($jalon, $client, $submittedAt);
        if ($fraudAlert && $fraudAlert->action_taken === 'payment_hold') {
            $jalon->update(['statut' => 'valide_suspendu']);
            Log::warning("[FRAUD PAYMENT HOLD] Jalon #{$jalon->id} suspendu suite à l'alerte #{$fraudAlert->reference}");

            $this->notificationService->send(
                $jalon->mission->artisan,
                'fraud_alert',
                'Contrôle de sécurité en cours',
                "La validation du jalon #{$jalon->ordre} fait l'objet d'une vérification de sécurité avant libération des fonds.",
                ['mission_id' => $jalon->mission_id, 'jalon_id' => $jalon->id]
            );

            return true;
        }

        // RÈGLE : missions > 2M FCFA → validation physique Référent requise
        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        if ($jalon->mission->montant_total > $seuil) {
            $jalon->mission->update(['referent_required' => true]);
            Log::info("[Référent requis] Mission #{$jalon->mission_id} > {$seuil} FCFA");

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

    /**
     * Libère un jalon sans OTP — réservé exclusivement au CRON Force-Pass 72h.
     * Guard : la mission ne doit pas être en litige.
     *
     * JUSTIFICATION BACKLOG (Epic 9) : si le client ne valide pas un jalon
     * en 72h (inaction ou mauvaise foi), le système libère automatiquement
     * les fonds afin de protéger l'artisan.
     */
    public function forceRelease(Jalon $jalon): void
    {
        if ($jalon->mission->status instanceof DisputedState) {
            Log::warning("[ForceRelease] Jalon #{$jalon->id} ignoré : mission en litige.", [
                'mission_id' => $jalon->mission_id,
            ]);

            return;
        }

        if ($jalon->statut !== 'soumis') {
            return; // Déjà traité
        }

        Log::info("[ForceRelease] Libération automatique du jalon #{$jalon->id} (72h dépassées).", [
            'mission_id' => $jalon->mission_id,
            'montant'    => $jalon->montant,
        ]);

        $jalon->update([
            'statut'    => 'valide',
            'valide_at' => now(),
            'otp_code'  => null,
        ]);

        $this->walletService->releaseJalon($jalon);

        $this->notificationService->send(
            $jalon->mission->artisan,
            'payment',
            'Paiement automatique reçu',
            "Le jalon #{$jalon->ordre} a été libéré automatiquement (le client n'a pas répondu sous 72h).",
            ['mission_id' => $jalon->mission_id, 'jalon_id' => $jalon->id]
        );
    }

    /**
     * Le client accepte directement les preuves de travail et valide le jalon sans OTP.
     */
    public function acceptProofs(Jalon $jalon): void
    {
        if ($jalon->mission->isFundsFrozen()) {
            throw ValidationException::withMessages([
                'jalon' => ['Les fonds de cette mission sont gelés en raison d\'un litige.'],
            ]);
        }

        $jalon->update([
            'statut'    => 'valide',
            'valide_at' => now(),
            'otp_code'  => null,
        ]);

        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        if ($jalon->mission->montant_total > $seuil) {
            $jalon->mission->update(['referent_required' => true]);
            Log::info("[Référent requis - Acceptation Directe] Mission #{$jalon->mission_id} > {$seuil} FCFA");
            return;
        }

        $this->walletService->releaseJalon($jalon);

        $this->notificationService->send(
            $jalon->mission->artisan,
            'payment',
            'Preuves acceptées !',
            "Le client a accepté vos preuves pour le jalon #{$jalon->ordre}. Paiement en cours.",
            ['mission_id' => $jalon->mission_id]
        );
    }
}
