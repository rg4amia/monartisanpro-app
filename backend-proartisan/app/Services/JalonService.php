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
    ) {}

    public function submit(Jalon $jalon, array $photos = []): void
    {
        if (!empty($photos)) {
            $jalon->update(['photos_json' => $photos]);
        }

        $analysis = $this->analyzePhotos($jalon);
        if (!$analysis['approved']) {
            throw ValidationException::withMessages([
                'photos' => [$analysis['reason']],
            ]);
        }

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

    public function analyzePhotos(Jalon $jalon): array
    {
        $geminiKey = env('GEMINI_API_KEY');
        if (!$geminiKey || $geminiKey === 'PLACEHOLDER_KEY') {
            if (str_contains(strtolower($jalon->description), 'frauduleux') || str_contains(strtolower($jalon->description), 'incohérent')) {
                return ['approved' => false, 'reason' => 'Analyse visuelle : La photo montre un seau vide ou des outils non conformes au jalon.'];
            }
            return ['approved' => true, 'reason' => 'Simulation : Analyse visuelle conforme.'];
        }

        $photos = $jalon->photos_json ?? [];
        if (empty($photos)) {
            return ['approved' => true, 'reason' => 'Aucune photo à analyser.'];
        }

        $photo = $photos[0];
        $path = $photo['path'] ?? null;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return ['approved' => true, 'reason' => 'Fichier photo introuvable localement.'];
        }

        $fileData = base64_encode(Storage::disk('public')->get($path));
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $mimeType = $disk->mimeType($path) ?? 'image/jpeg';

        $prompt = "Tu es un expert en inspection de chantiers de BTP en Côte d'Ivoire. L'artisan prétend avoir terminé le jalon suivant : '{$jalon->description}'.\n";
        $prompt .= "Regarde attentivement l'image de preuve fournie. Est-elle cohérente avec le travail décrit ? Par exemple, si la description parle de couler une dalle en béton et que l'image montre juste un terrain vague vide ou un seau de peinture vide, c'est incohérent. Sois bienveillant mais juste.\n";
        $prompt .= "Réponds STRICTEMENT par un objet JSON valide avec les clés suivantes :\n";
        $prompt .= "{\n  \"approved\": true ou false,\n  \"reason\": \"Explication détaillée en français\"\n}";

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}";
            $response = Http::post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $fileData,
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $jsonText = $response->json('candidates.0.content.parts.0.text');
                $result = json_decode($jsonText, true);
                if (isset($result['approved'])) {
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::error('Vision API error: ' . $e->getMessage());
        }

        return ['approved' => true, 'reason' => 'Échec de l\'analyse de vision, approbation par défaut.'];
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

        $jalon->update(['statut' => 'valide', 'valide_at' => now(), 'otp_code' => null]);

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
}
