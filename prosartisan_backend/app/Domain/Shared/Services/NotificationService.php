<?php

namespace App\Domain\Shared\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\ValueObjects\NotificationPreferences;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private array $channels;

    private UserRepository $userRepository;

    public function __construct(
        UserRepository $userRepository,
        PushNotificationService $pushService,
        SMSNotificationService $smsService,
        WhatsAppNotificationService $whatsappService,
        EmailNotificationService $emailService
    ) {
        $this->userRepository = $userRepository;
        $this->channels = [
            'push' => $pushService,
            'sms' => $smsService,
            'whatsapp' => $whatsappService,
            'email' => $emailService,
        ];
    }

    /**
     * Send notification with retry logic across channels
     */
    public function send(UserId $userId, string $title, string $message, array $data = []): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (! $user) {
                Log::warning("User not found: {$userId->getValue()}");

                return false;
            }

            $preferences = $user->getNotificationPreferences();
            $channelOrder = $preferences->getChannelOrder();

            foreach ($channelOrder as $channelName) {
                if (! $preferences->isChannelEnabled($channelName)) {
                    continue;
                }

                if (! isset($this->channels[$channelName])) {
                    continue;
                }

                $channel = $this->channels[$channelName];

                if (! $channel->isAvailable()) {
                    Log::warning("Channel {$channelName} is not available");

                    continue;
                }

                Log::info("Attempting to send notification via {$channelName} to user {$userId->getValue()}");

                if ($channel->send($userId, $title, $message, $data)) {
                    Log::info("Notification sent successfully via {$channelName} to user {$userId->getValue()}");

                    return true;
                }

                Log::warning("Failed to send notification via {$channelName} to user {$userId->getValue()}, trying next channel");
            }

            Log::error("Failed to send notification to user {$userId->getValue()} via all available channels");

            return false;
        } catch (\Exception $e) {
            Log::error("Exception while sending notification to user {$userId->getValue()}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMultiple(array $userIds, string $title, string $message, array $data = []): array
    {
        $results = [];

        foreach ($userIds as $userId) {
            if (! $userId instanceof UserId) {
                continue;
            }

            $results[$userId->getValue()] = $this->send($userId, $title, $message, $data);
        }

        return $results;
    }

    /**
     * Send notification via specific channel
     */
    public function sendViaChannel(string $channelName, UserId $userId, string $title, string $message, array $data = []): bool
    {
        if (! isset($this->channels[$channelName])) {
            Log::error("Unknown notification channel: {$channelName}");

            return false;
        }

        $channel = $this->channels[$channelName];

        if (! $channel->isAvailable()) {
            Log::error("Channel {$channelName} is not available");

            return false;
        }

        return $channel->send($userId, $title, $message, $data);
    }

    /**
     * Send push notification to topic (for nearby artisans)
     */
    public function sendToTopic(string $topic, string $title, string $message, array $data = []): bool
    {
        $pushService = $this->channels['push'];

        if (! $pushService instanceof PushNotificationService) {
            return false;
        }

        return $pushService->sendToTopic($topic, $title, $message, $data);
    }

    /**
     * Subscribe user to topic
     */
    public function subscribeToTopic(UserId $userId, string $topic): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (! $user || ! $user->getDeviceToken()) {
                return false;
            }

            $pushService = $this->channels['push'];

            if (! $pushService instanceof PushNotificationService) {
                return false;
            }

            return $pushService->subscribeToTopic($user->getDeviceToken(), $topic);
        } catch (\Exception $e) {
            Log::error("Failed to subscribe user {$userId->getValue()} to topic {$topic}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Unsubscribe user from topic
     */
    public function unsubscribeFromTopic(UserId $userId, string $topic): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (! $user || ! $user->getDeviceToken()) {
                return false;
            }

            $pushService = $this->channels['push'];

            if (! $pushService instanceof PushNotificationService) {
                return false;
            }

            return $pushService->unsubscribeFromTopic($user->getDeviceToken(), $topic);
        } catch (\Exception $e) {
            Log::error("Failed to unsubscribe user {$userId->getValue()} from topic {$topic}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Update user notification preferences
     */
    public function updateUserPreferences(UserId $userId, NotificationPreferences $preferences): bool
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (! $user) {
                return false;
            }

            $user->updateNotificationPreferences($preferences);
            $this->userRepository->save($user);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update notification preferences for user {$userId->getValue()}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Get available channels
     */
    public function getAvailableChannels(): array
    {
        $available = [];

        foreach ($this->channels as $name => $channel) {
            if ($channel->isAvailable()) {
                $available[] = $name;
            }
        }

        return $available;
    }
}
