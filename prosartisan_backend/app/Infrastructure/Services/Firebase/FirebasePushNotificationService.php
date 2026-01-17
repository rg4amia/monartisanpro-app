<?php

namespace App\Infrastructure\Services\Firebase;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\PushNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushNotificationService implements PushNotificationService
{
 private string $serverKey;
 private string $projectId;
 private UserRepository $userRepository;

 public function __construct(UserRepository $userRepository)
 {
  $this->serverKey = config('services.firebase.server_key');
  $this->projectId = config('services.firebase.project_id');
  $this->userRepository = $userRepository;
 }

 public function send(UserId $userId, string $title, string $message, array $data = []): bool
 {
  try {
   $user = $this->userRepository->findById($userId);
   if (!$user || !$user->getDeviceToken()) {
    Log::warning("No device token found for user {$userId->getValue()}");
    return false;
   }

   return $this->sendToDevice($user->getDeviceToken(), $title, $message, $data);
  } catch (\Exception $e) {
   Log::error("Failed to send push notification to user {$userId->getValue()}: " . $e->getMessage());
   return false;
  }
 }

 public function sendToDevice(string $deviceToken, string $title, string $message, array $data = []): bool
 {
  try {
   $payload = [
    'to' => $deviceToken,
    'notification' => [
     'title' => $title,
     'body' => $message,
     'sound' => 'default',
     'badge' => 1,
    ],
    'data' => $data,
    'priority' => 'high',
   ];

   $response = Http::withHeaders([
    'Authorization' => 'key=' . $this->serverKey,
    'Content-Type' => 'application/json',
   ])->post('https://fcm.googleapis.com/fcm/send', $payload);

   if ($response->successful()) {
    $result = $response->json();
    return $result['success'] > 0;
   }

   Log::error('Firebase push notification failed', [
    'status' => $response->status(),
    'response' => $response->body(),
   ]);

   return false;
  } catch (\Exception $e) {
   Log::error('Firebase push notification exception: ' . $e->getMessage());
   return false;
  }
 }

 public function sendToTopic(string $topic, string $title, string $message, array $data = []): bool
 {
  try {
   $payload = [
    'to' => '/topics/' . $topic,
    'notification' => [
     'title' => $title,
     'body' => $message,
     'sound' => 'default',
    ],
    'data' => $data,
    'priority' => 'high',
   ];

   $response = Http::withHeaders([
    'Authorization' => 'key=' . $this->serverKey,
    'Content-Type' => 'application/json',
   ])->post('https://fcm.googleapis.com/fcm/send', $payload);

   if ($response->successful()) {
    $result = $response->json();
    return $result['success'] > 0;
   }

   return false;
  } catch (\Exception $e) {
   Log::error('Firebase topic notification exception: ' . $e->getMessage());
   return false;
  }
 }

 public function subscribeToTopic(string $deviceToken, string $topic): bool
 {
  try {
   $response = Http::withHeaders([
    'Authorization' => 'key=' . $this->serverKey,
    'Content-Type' => 'application/json',
   ])->post("https://iid.googleapis.com/iid/v1/{$deviceToken}/rel/topics/{$topic}");

   return $response->successful();
  } catch (\Exception $e) {
   Log::error('Firebase topic subscription exception: ' . $e->getMessage());
   return false;
  }
 }

 public function unsubscribeFromTopic(string $deviceToken, string $topic): bool
 {
  try {
   $response = Http::withHeaders([
    'Authorization' => 'key=' . $this->serverKey,
    'Content-Type' => 'application/json',
   ])->delete("https://iid.googleapis.com/iid/v1/{$deviceToken}/rel/topics/{$topic}");

   return $response->successful();
  } catch (\Exception $e) {
   Log::error('Firebase topic unsubscription exception: ' . $e->getMessage());
   return false;
  }
 }

 public function getName(): string
 {
  return 'firebase_push';
 }

 public function isAvailable(): bool
 {
  return !empty($this->serverKey) && !empty($this->projectId);
 }
}
