<?php

namespace App\Infrastructure\Services\SMS;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\SMSNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSMSService implements SMSNotificationService
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private UserRepository $userRepository;

    public function
