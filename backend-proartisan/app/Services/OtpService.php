<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $length;
    private int $ttlMinutes;
    private SmsService $smsService;
    private WhatsAppService $whatsAppService;

    public function __construct(SmsService $smsService, WhatsAppService $whatsAppService)
    {
        $this->length          = config('prosartisan.otp.length', 4);
        $this->ttlMinutes      = config('prosartisan.otp.ttl', 5);
        $this->smsService      = $smsService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Génère et envoie un OTP par SMS ou WhatsApp.
     */
    public function sendOtp(string $phone, ?string $action = null, string $channel = 'sms'): string
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

        if (strtolower($channel) === 'whatsapp') {
            // Send via WhatsApp
            $result = $this->whatsAppService->sendOtp($phone, $otp);

            if ($result['status'] !== 'success') {
                Log::error('[OTP] Failed to send WhatsApp', [
                    'phone' => $phone,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
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
     */
    public function verifyOtp(string $phone, string $code, ?string $action = null): bool
    {
        $query = Otp::where('phone', $phone)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->whereNull('used_at');

        if ($action !== null) {
            $query->where('action', $action);
        }

        $otpRecord = $query->latest()->first();

        if (!$otpRecord) {
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
