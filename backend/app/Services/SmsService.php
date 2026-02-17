<?php

namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SmsService
{
    protected $gateway;
    protected $username;
    protected $apiKey;
    protected $shortcode;

    public function __construct()
    {
        $this->username = config('services.africastalking.username');
        $this->apiKey = config('services.africastalking.api_key');
        $this->shortcode = config('services.africastalking.shortcode', '40520');

        $this->gateway = new AfricasTalking(
            $this->username,
            $this->apiKey
        );
    }

    /**
     * Send OTP via SMS
     *
     * @param string $phone Phone number in international format (+225...)
     * @param string $code OTP code (6 digits)
     * @param string $purpose Purpose of OTP (registration, milestone_validation, etc.)
     * @return bool Success status
     */
    public function sendOtp(string $phone, string $code, string $purpose = 'verification'): bool
    {
        // Rate limiting: max 3 OTP per hour per phone
        $rateLimitKey = 'sms-otp:' . $phone;

        if (!RateLimiter::attempt($rateLimitKey, 3, function () {}, 3600)) {
            Log::channel('sms')->warning('OTP rate limit exceeded', [
                'phone' => $phone,
                'purpose' => $purpose,
            ]);
            return false;
        }

        // Format phone number (ensure +225 prefix for Côte d'Ivoire)
        $formattedPhone = $this->formatPhoneNumber($phone);

        // Generate message based on purpose
        $message = $this->getOtpMessage($code, $purpose);

        try {
            $sms = $this->gateway->sms();

            $result = $sms->send([
                'to' => $formattedPhone,
                'message' => $message,
                'from' => $this->shortcode,
            ]);

            // Africa's Talking response structure
            $recipients = $result['data']['SMSMessageData']['Recipients'] ?? [];

            if (!empty($recipients) && $recipients[0]['status'] === 'Success') {
                Log::channel('sms')->info('OTP sent successfully', [
                    'phone' => $formattedPhone,
                    'code' => $code,
                    'purpose' => $purpose,
                    'messageId' => $recipients[0]['messageId'] ?? null,
                    'cost' => $recipients[0]['cost'] ?? null,
                ]);

                // Cache OTP for verification (5 minutes expiry)
                $this->cacheOtp($phone, $code);

                return true;
            }

            Log::channel('sms')->error('OTP send failed', [
                'phone' => $formattedPhone,
                'purpose' => $purpose,
                'response' => $result,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::channel('sms')->error('SMS service exception', [
                'phone' => $formattedPhone,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Verify OTP code
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $cacheKey = 'otp:' . $phone;
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode && $cachedCode === $code) {
            // Delete OTP after successful verification (one-time use)
            Cache::forget($cacheKey);

            Log::channel('sms')->info('OTP verified successfully', [
                'phone' => $phone,
            ]);

            return true;
        }

        Log::channel('sms')->warning('OTP verification failed', [
            'phone' => $phone,
            'code' => $code,
            'cached_code' => $cachedCode,
        ]);

        return false;
    }

    /**
     * Generate OTP code
     *
     * @param int $length
     * @return string
     */
    public function generateOtp(int $length = 6): string
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;

        return (string) random_int($min, $max);
    }

    /**
     * Send general SMS notification
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function sendNotification(string $phone, string $message): bool
    {
        $formattedPhone = $this->formatPhoneNumber($phone);

        try {
            $sms = $this->gateway->sms();

            $result = $sms->send([
                'to' => $formattedPhone,
                'message' => $message,
                'from' => $this->shortcode,
            ]);

            $recipients = $result['data']['SMSMessageData']['Recipients'] ?? [];

            if (!empty($recipients) && $recipients[0]['status'] === 'Success') {
                Log::channel('sms')->info('Notification SMS sent', [
                    'phone' => $formattedPhone,
                    'messageId' => $recipients[0]['messageId'] ?? null,
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::channel('sms')->error('Notification SMS failed', [
                'phone' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send bulk SMS notifications
     *
     * @param array $phones Array of phone numbers
     * @param string $message
     * @return array Results per phone
     */
    public function sendBulk(array $phones, string $message): array
    {
        $results = [];

        foreach ($phones as $phone) {
            $results[$phone] = $this->sendNotification($phone, $message);
        }

        return $results;
    }

    /**
     * Format phone number for Côte d'Ivoire
     *
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove spaces and special characters
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // If starts with 0, replace with +225
        if (substr($cleaned, 0, 1) === '0') {
            $cleaned = '+225' . substr($cleaned, 1);
        }

        // If doesn't start with +, add +225
        if (substr($cleaned, 0, 1) !== '+') {
            $cleaned = '+225' . $cleaned;
        }

        // If starts with 225 but no +, add +
        if (substr($cleaned, 0, 3) === '225' && substr($cleaned, 0, 1) !== '+') {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Get OTP message based on purpose
     *
     * @param string $code
     * @param string $purpose
     * @return string
     */
    protected function getOtpMessage(string $code, string $purpose): string
    {
        $messages = [
            'registration' => "Votre code de vérification ProsArtisan: {$code}. Valide 5 minutes. Ne le partagez pas.",
            'login' => "Code de connexion ProsArtisan: {$code}. Valide 5 minutes.",
            'milestone_validation' => "Code de validation étape: {$code}. Entrez ce code pour confirmer l'étape du projet. Valide 5 minutes.",
            'password_reset' => "Code de réinitialisation ProsArtisan: {$code}. Valide 5 minutes.",
            'verification' => "Votre code ProsArtisan: {$code}. Valide 5 minutes.",
        ];

        return $messages[$purpose] ?? $messages['verification'];
    }

    /**
     * Cache OTP for verification
     *
     * @param string $phone
     * @param string $code
     * @return void
     */
    protected function cacheOtp(string $phone, string $code): void
    {
        $cacheKey = 'otp:' . $phone;
        $expiryMinutes = 5; // OTP valid for 5 minutes

        Cache::put($cacheKey, $code, now()->addMinutes($expiryMinutes));
    }

    /**
     * Get account balance (for monitoring)
     *
     * @return array|null
     */
    public function getBalance(): ?array
    {
        try {
            $application = $this->gateway->application();
            $result = $application->fetchApplicationData();

            return [
                'balance' => $result['data']['UserData']['balance'] ?? null,
                'currency' => 'USD',
            ];
        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to fetch balance', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
