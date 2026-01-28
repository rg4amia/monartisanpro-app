<?php

namespace App\Infrastructure\Services\WhatsApp;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppBusinessService implements WhatsAppNotificationService
{
    private string $accessToken;

    private string $phoneNumberId;

    private string $businessAccountId;

    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->businessAccountId = config('services.whatsapp.business_account_id');
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

            $fullMessage = "*{$title}*\n\n{$message}";

            return $this->sendMessage($user->getPhoneNumber(), $fullMessage);
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message to user {$userId->getValue()}: ".$e->getMessage());

            return false;
        }
    }

    public function sendMessage(PhoneNumber $phoneNumber, string $message): bool
    {
        try {
            $formattedNumber = $this->formatPhoneNumber($phoneNumber->getValue());

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $formattedNumber,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return isset($result['messages'][0]['id']);
            }

            Log::error('WhatsApp message failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp message exception: '.$e->getMessage());

            return false;
        }
    }

    public function sendTemplate(PhoneNumber $phoneNumber, string $templateName, array $parameters = []): bool
    {
        try {
            $formattedNumber = $this->formatPhoneNumber($phoneNumber->getValue());

            $templateComponents = [];
            if (! empty($parameters)) {
                $templateComponents[] = [
                    'type' => 'body',
                    'parameters' => array_map(fn ($param) => ['type' => 'text', 'text' => $param], $parameters),
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $formattedNumber,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'fr',
                    ],
                    'components' => $templateComponents,
                ],
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return isset($result['messages'][0]['id']);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp template exception: '.$e->getMessage());

            return false;
        }
    }

    public function sendMedia(PhoneNumber $phoneNumber, string $mediaUrl, string $caption = ''): bool
    {
        try {
            $formattedNumber = $this->formatPhoneNumber($phoneNumber->getValue());

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $formattedNumber,
                'type' => 'image',
                'image' => [
                    'link' => $mediaUrl,
                    'caption' => $caption,
                ],
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return isset($result['messages'][0]['id']);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp media exception: '.$e->getMessage());

            return false;
        }
    }

    public function getName(): string
    {
        return 'whatsapp_business';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->accessToken) && ! empty($this->phoneNumberId);
    }

    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/\D/', '', $phoneNumber);

        // If it starts with 225, it's already formatted
        if (str_starts_with($cleaned, '225')) {
            return $cleaned;
        }

        // If it starts with 0, replace with 225
        if (str_starts_with($cleaned, '0')) {
            return '225'.substr($cleaned, 1);
        }

        // Otherwise, assume it's a local number and add 225
        return '225'.$cleaned;
    }
}
