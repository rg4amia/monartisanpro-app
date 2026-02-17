<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = config('services.firebase.credentials');

        if (!file_exists($credentialsPath)) {
            Log::error('Firebase credentials file not found', [
                'path' => $credentialsPath,
            ]);
            return;
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send notification to a single user
     *
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @param string|null $image Optional image URL
     * @return bool Success status
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): bool {
        $user = User::find($userId);

        if (!$user || !$user->fcm_token) {
            Log::warning('User not found or no FCM token', [
                'user_id' => $userId,
            ]);
            return false;
        }

        return $this->sendToToken($user->fcm_token, $title, $body, $data, $image);
    }

    /**
     * Send notification to specific FCM token
     *
     * @param string $token FCM device token
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string|null $image
     * @return bool
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): bool {
        try {
            $notification = Notification::create($title, $body);

            if ($image) {
                $notification = $notification->withImageUrl($image);
            }

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data)
                ->withAndroidConfig($this->getAndroidConfig($data))
                ->withApnsConfig($this->getApnsConfig($data));

            $this->messaging->send($message);

            Log::channel('default')->info('FCM notification sent', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'data' => $data,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM send failed', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            // If token is invalid, clear it from user record
            if (str_contains($e->getMessage(), 'not-found') ||
                str_contains($e->getMessage(), 'invalid-registration-token')) {
                User::where('fcm_token', $token)->update(['fcm_token' => null]);
            }

            return false;
        }
    }

    /**
     * Send notification to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string|null $image
     * @return array Results with success count
     */
    public function sendToMultiple(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): array {
        $tokens = User::whereIn('id', $userIds)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->toArray();

        if (empty($tokens)) {
            return [
                'success' => false,
                'sent' => 0,
                'failed' => count($userIds),
            ];
        }

        return $this->sendMulticast($tokens, $title, $body, $data, $image);
    }

    /**
     * Send multicast notification to multiple tokens
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string|null $image
     * @return array
     */
    public function sendMulticast(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?string $image = null
    ): array {
        try {
            $notification = Notification::create($title, $body);

            if ($image) {
                $notification = $notification->withImageUrl($image);
            }

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data)
                ->withAndroidConfig($this->getAndroidConfig($data))
                ->withApnsConfig($this->getApnsConfig($data));

            $report = $this->messaging->sendMulticast($message, $tokens);

            Log::channel('default')->info('FCM multicast sent', [
                'total' => count($tokens),
                'success' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
            ]);

            // Clean up invalid tokens
            foreach ($report->invalidTokens() as $invalidToken) {
                User::where('fcm_token', $invalidToken)->update(['fcm_token' => null]);
            }

            return [
                'success' => true,
                'sent' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
            ];
        } catch (\Exception $e) {
            Log::error('FCM multicast failed', [
                'tokens_count' => count($tokens),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'sent' => 0,
                'failed' => count($tokens),
            ];
        }
    }

    /**
     * Send notification to a topic
     *
     * @param string $topic Topic name (e.g., 'artisans', 'clients')
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::channel('default')->info('FCM topic notification sent', [
                'topic' => $topic,
                'title' => $title,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM topic send failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Subscribe user to topic
     *
     * @param int $userId
     * @param string $topic
     * @return bool
     */
    public function subscribeToTopic(int $userId, string $topic): bool
    {
        $user = User::find($userId);

        if (!$user || !$user->fcm_token) {
            return false;
        }

        try {
            $this->messaging->subscribeToTopic($topic, [$user->fcm_token]);

            Log::channel('default')->info('User subscribed to topic', [
                'user_id' => $userId,
                'topic' => $topic,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Topic subscription failed', [
                'user_id' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Unsubscribe user from topic
     *
     * @param int $userId
     * @param string $topic
     * @return bool
     */
    public function unsubscribeFromTopic(int $userId, string $topic): bool
    {
        $user = User::find($userId);

        if (!$user || !$user->fcm_token) {
            return false;
        }

        try {
            $this->messaging->unsubscribeFromTopic($topic, [$user->fcm_token]);

            Log::channel('default')->info('User unsubscribed from topic', [
                'user_id' => $userId,
                'topic' => $topic,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Topic unsubscription failed', [
                'user_id' => $userId,
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get Android-specific configuration
     *
     * @param array $data
     * @return AndroidConfig
     */
    protected function getAndroidConfig(array $data): AndroidConfig
    {
        return AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'sound' => 'default',
                'color' => '#FF6B35', // ProsArtisan brand color
                'channel_id' => 'prosartisan_notifications',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ]);
    }

    /**
     * Get iOS-specific configuration
     *
     * @param array $data
     * @return ApnsConfig
     */
    protected function getApnsConfig(array $data): ApnsConfig
    {
        return ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                    'badge' => 1,
                    'content-available' => 1,
                ],
            ],
        ]);
    }

    /**
     * Pre-defined notification templates
     */

    /**
     * KYC Approved Notification
     */
    public function sendKycApproved(int $userId): bool
    {
        return $this->sendToUser(
            $userId,
            'KYC Approuvé ✅',
            'Votre vérification d\'identité a été approuvée. Vous pouvez maintenant utiliser toutes les fonctionnalités.',
            [
                'type' => 'kyc_approved',
                'action' => 'open_profile',
            ]
        );
    }

    /**
     * New Quote Received Notification
     */
    public function sendNewQuote(int $userId, string $artisanName, string $projectTitle, int $quoteId): bool
    {
        return $this->sendToUser(
            $userId,
            'Nouveau Devis Reçu 📄',
            "Vous avez reçu un nouveau devis de $artisanName pour votre projet $projectTitle.",
            [
                'type' => 'quote_received',
                'quote_id' => (string) $quoteId,
                'action' => 'view_quote',
            ]
        );
    }

    /**
     * Payment Confirmed Notification
     */
    public function sendPaymentConfirmed(int $userId, float $amount, int $projectId): bool
    {
        return $this->sendToUser(
            $userId,
            'Paiement Confirmé 💰',
            "Votre paiement de " . number_format($amount, 0, ',', ' ') . " XOF a été confirmé. Le projet peut commencer.",
            [
                'type' => 'payment_confirmed',
                'project_id' => (string) $projectId,
                'amount' => (string) $amount,
                'action' => 'view_project',
            ]
        );
    }

    /**
     * Milestone Validated Notification
     */
    public function sendMilestoneValidated(int $userId, string $milestoneTitle, int $milestoneId): bool
    {
        return $this->sendToUser(
            $userId,
            'Étape Validée ✅',
            "L'étape $milestoneTitle a été validée. Paiement programmé J+1.",
            [
                'type' => 'milestone_validated',
                'milestone_id' => (string) $milestoneId,
                'action' => 'view_milestone',
            ]
        );
    }

    /**
     * Project Completed Notification
     */
    public function sendProjectCompleted(int $userId, string $projectTitle, int $projectId): bool
    {
        return $this->sendToUser(
            $userId,
            'Projet Terminé 🎉',
            "Le projet $projectTitle est terminé. Merci de laisser un avis pour votre artisan.",
            [
                'type' => 'project_completed',
                'project_id' => (string) $projectId,
                'action' => 'leave_review',
            ]
        );
    }

    /**
     * Review Received Notification
     */
    public function sendReviewReceived(int $userId, float $rating, string $clientName): bool
    {
        $stars = str_repeat('⭐', (int) round($rating));

        return $this->sendToUser(
            $userId,
            'Nouvel Avis Reçu',
            "$clientName vous a laissé un avis: $stars",
            [
                'type' => 'review_received',
                'rating' => (string) $rating,
                'action' => 'view_reviews',
            ]
        );
    }

    /**
     * Score Updated Notification
     */
    public function sendScoreUpdated(int $userId, float $newScore, string $badgeLevel): bool
    {
        $badgeEmoji = [
            'gold' => '🥇',
            'silver' => '🥈',
            'bronze' => '🥉',
        ];

        $emoji = $badgeEmoji[$badgeLevel] ?? '';

        return $this->sendToUser(
            $userId,
            "Score N'Zassa Mis à Jour $emoji",
            "Votre nouveau score: " . number_format($newScore, 1) . "/100. Badge: " . ucfirst($badgeLevel),
            [
                'type' => 'score_updated',
                'score' => (string) $newScore,
                'badge' => $badgeLevel,
                'action' => 'view_score',
            ]
        );
    }
}
