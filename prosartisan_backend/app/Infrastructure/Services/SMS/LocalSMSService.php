<?php

namespace App\Infrastructure\Services\SMS;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\SMSNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalSMSService implements SMSNotificationService
{
    private string $apiUrl;

    private string $apiKey;

    private string $senderId;

    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->apiUrl = config('services.local_sms.api_url');
        $this->apiKey = config('services.local_sms.api_key');
        $this->senderId = config('services.local_sms.sender_id', 'ProSartisan');
        $this->userRepository = $userRepository;
    }

    public function send(UserId $userId, string $title, string $message, array $data = []): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (! $user || ! $user->getPhoneNumber()) {
                Log::warning("No phone number found for user {$userId->getValue()}");

                return false;
            }

            $fullMessage = $title."\n\n".$message;

            return $this->sendSMS($user->getPhoneNumber(), $fullMessage);
        } catch (\Exception $e) {
            Log::error("Failed to send SMS to user {$userId->getValue()}: ".$e->getMessage());

            return false;
        }
    }

    public function sendSMS(PhoneNumber $phoneNumber, string $message): bool
    {
        try {
            // Format phone number for Côte d'Ivoire (+225)
            $formattedNumber = $this->formatPhoneNumber($phoneNumber->getValue());

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl.'/send', [
                'sender' => $this->senderId,
                'recipient' => $formattedNumber,
                'message' => $message,
                'type' => 'text',
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return $result['status'] === 'success';
            }

            Log::error('Local SMS failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Local SMS exception: '.$e->getMessage());

            return false;
        }
    }

    public function sendOTP(PhoneNumber $phoneNumber, string $code): bool
    {
        $message = "Votre code de vérification ProSartisan est: {$code}. Ce code expire dans 5 minutes.";

        return $this->sendSMS($phoneNumber, $message);
    }

    public function getName(): string
    {
        return 'local_sms';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiUrl) && ! empty($this->apiKey);
    }

    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/\D/', '', $phoneNumber);

        // If it starts with 225, it's already formatted
        if (str_starts_with($cleaned, '225')) {
            return '+'.$cleaned;
        }

        // If it starts with 0, replace with 225
        if (str_starts_with($cleaned, '0')) {
            return '+225'.substr($cleaned, 1);
        }

        // Otherwise, assume it's a local number and add 225
        return '+225'.$cleaned;
    }
}
