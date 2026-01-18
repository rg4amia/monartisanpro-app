<?php

namespace App\Infrastructure\Services\SMS;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\SMSNotificationService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSMSService implements SMSNotificationService
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->accountSid = config('services.twilio.account_sid');
        $this->authToken = config('services.twilio.auth_token');
        $this->fromNumber = config('services.twilio.from_number');
        $this->userRepository = $userRepository;
    }

    public function send(UserId $userId, string $title, string $message, array $data = []): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (!$user || !$user->getPhoneNumber()) {
                Log::warning("No phone number found for user {$userId->getValue()}");
                return false;
            }

            $fullMessage = $title . "\n\n" . $message;
            return $this->sendSMS($user->getPhoneNumber(), $fullMessage);
        } catch (\Exception $e) {
            Log::error("Failed to send SMS to user {$userId->getValue()}: " . $e->getMessage());
            return false;
        }
    }

    public function sendSMS(PhoneNumber $phoneNumber, string $message): bool
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'From' => $this->fromNumber,
                    'To' => $phoneNumber->getValue(),
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                return isset($result['sid']);
            }

            Log::error('Twilio SMS failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Twilio SMS exception: ' . $e->getMessage());
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
        return 'twilio_sms';
    }

    public function isAvailable(): bool
    {
        return !empty($this->accountSid) && !empty($this->authToken) && !empty($this->fromNumber);
    }
}
