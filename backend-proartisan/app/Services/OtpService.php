<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $length;
    private int $ttlMinutes;

    public function __construct()
    {
        $this->length     = config('prosartisan.otp.length', 4);
        $this->ttlMinutes = config('prosartisan.otp.ttl', 5);
    }

    /**
     * Génère et envoie un OTP par SMS (stub log pour dev).
     */
    public function sendOtp(string $phone): string
    {
        $otp = $this->generate();

        Cache::put($this->cacheKey($phone), $otp, now()->addMinutes($this->ttlMinutes));

        // En production : remplacer par Infobip/Twilio
        Log::info("[OTP STUB] Téléphone: {$phone} | Code: {$otp} | Expire dans {$this->ttlMinutes} min");

        return $otp;
    }

    /**
     * Vérifie l'OTP et l'invalide s'il est correct.
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $key   = $this->cacheKey($phone);
        $stored = Cache::get($key);

        if ($stored === null || $stored !== $code) {
            return false;
        }

        Cache::forget($key);

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

    private function cacheKey(string $phone): string
    {
        return 'otp:' . $phone;
    }
}
