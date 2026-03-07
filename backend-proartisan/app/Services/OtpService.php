<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $length;
    private int $ttlMinutes;
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->length     = config('prosartisan.otp.length', 4);
        $this->ttlMinutes = config('prosartisan.otp.ttl', 5);
        $this->smsService = $smsService;
    }

    /**
     * Génère et envoie un OTP par SMS via SMS Pro Africa.
     */
    public function sendOtp(string $phone): string
    {
        $otp = $this->generate();

        Cache::put($this->cacheKey($phone), $otp, now()->addMinutes($this->ttlMinutes));

        // Send via SMS Pro Africa
        $result = $this->smsService->sendOtp($phone, $otp);

        if ($result['status'] !== 'success') {
            Log::error('[OTP] Failed to send SMS', [
                'phone' => $phone,
                'error' => $result['message'] ?? 'Unknown error',
            ]);
        }

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
