<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $length;
    private int $ttlMinutes;
    private int $maxAttempts;
    private SmsService $smsService;
    private WhatsAppService $whatsAppService;

    public function __construct(SmsService $smsService, WhatsAppService $whatsAppService)
    {
        $this->length          = config('prosartisan.otp.length', 4);
        $this->ttlMinutes      = config('prosartisan.otp.ttl', 5);
        $this->maxAttempts     = config('prosartisan.otp.max_attempts', 5);
        $this->smsService      = $smsService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Génère et envoie un OTP par SMS ou WhatsApp.
     */
    public function sendOtp(string $phone, ?string $action = null, ?string $channel = null): string
    {
        $otp = $this->generate();

        $user = User::where('phone', $phone)->first();

        Otp::create([
            'phone'      => $phone,
            'user_id'    => $user?->id,
            'code'       => $otp,
            'action'     => $action,
            'expires_at' => now()->addMinutes($this->ttlMinutes),
        ]);

        $globalChannel = \App\Models\Setting::getValueByKey('otp_delivery_channel', 'sms');
        $resolvedChannel = $channel ?: $globalChannel;

        if (strtolower($resolvedChannel) === 'both') {
            // Envoi par SMS
            $smsResult = $this->smsService->sendOtp($phone, $otp);
            if ($smsResult['status'] !== 'success') {
                Log::error('[OTP] Failed to send SMS (both)', [
                    'phone' => $phone,
                    'error' => $smsResult['message'] ?? 'Unknown error',
                ]);
            }

            // Envoi par WhatsApp
            $waResult = $this->whatsAppService->sendOtp($phone, $otp);
            if ($waResult['status'] !== 'success') {
                Log::error('[OTP] Failed to send WhatsApp (both)', [
                    'phone' => $phone,
                    'error' => $waResult['message'] ?? 'Unknown error',
                ]);
            }
        } elseif (strtolower($resolvedChannel) === 'whatsapp') {
            // Send via WhatsApp
            $result = $this->whatsAppService->sendOtp($phone, $otp);

            if ($result['status'] !== 'success') {
                Log::error('[OTP] Failed to send WhatsApp', [
                    'phone' => $phone,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                // Repli SMS : un canal WhatsApp mal configuré (token absent,
                // endpoint invalide...) ne doit jamais bloquer totalement la
                // livraison de l'OTP, qui est le mécanisme de connexion.
                $fallbackResult = $this->smsService->sendOtp($phone, $otp);
                if ($fallbackResult['status'] !== 'success') {
                    Log::error('[OTP] Failed to send SMS (repli WhatsApp)', [
                        'phone' => $phone,
                        'error' => $fallbackResult['message'] ?? 'Unknown error',
                    ]);
                }
            }
        } else {
            // Send via SMS
            $result = $this->smsService->sendOtp($phone, $otp);

            if ($result['status'] !== 'success') {
                Log::error('[OTP] Failed to send SMS', [
                    'phone' => $phone,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
            }
        }

        return $otp;
    }

    /**
     * Vérifie l'OTP et l'invalide s'il est correct.
     *
     * La recherche porte sur le dernier OTP actif du numéro — et non sur le
     * couple (numéro, code) — afin de pouvoir compter les échecs : un code
     * erroné doit coûter une tentative. Passé [maxAttempts], le code est brûlé
     * et l'utilisateur doit en redemander un, ce qui rend la force brute
     * inopérante même distribuée sur de nombreuses IP.
     */
    public function verifyOtp(string $phone, string $code, ?string $action = null): bool
    {
        $query = Otp::where('phone', $phone)
            ->where('expires_at', '>', now())
            ->whereNull('used_at');

        if ($action !== null) {
            $query->where('action', $action);
        }

        // Tri par `id` et non par `created_at` : deux OTP émis dans la même
        // seconde rendraient l'ordre par date ambigu, et l'on sélectionnerait
        // parfois le code précédent — le dernier code envoyé serait alors
        // refusé. L'identifiant auto-incrémenté lève cette ambiguïté.
        $otpRecord = $query->latest('id')->first();

        if (!$otpRecord) {
            return false;
        }

        // hash_equals : comparaison à temps constant.
        if (!hash_equals($otpRecord->code, $code)) {
            $otpRecord->increment('attempts');

            if ($otpRecord->attempts >= $this->maxAttempts) {
                // Le code est brûlé : il ne pourra plus être validé, même
                // avec la bonne valeur, avant un nouvel envoi.
                $otpRecord->update(['used_at' => now()]);

                Log::warning('[OTP] Code invalidé après trop de tentatives', [
                    'phone'    => $phone,
                    'action'   => $action,
                    'attempts' => $otpRecord->attempts,
                ]);
            }

            return false;
        }

        $otpRecord->update(['used_at' => now()]);

        return true;
    }

    /**
     * Retourne le temps restant avant expiration (secondes).
     */
    public function ttlSeconds(): int
    {
        return $this->ttlMinutes * 60;
    }

    private function generate(): string
    {
        return str_pad((string) random_int(0, (int) str_repeat('9', $this->length)), $this->length, '0', STR_PAD_LEFT);
    }
}
