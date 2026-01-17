<?php

namespace App\Infrastructure\Services\Email;

use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\EmailNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LaravelEmailService implements EmailNotificationService
{
 private UserRepository $userRepository;

 public function __construct(UserRepository $userRepository)
 {
  $this->userRepository = $userRepository;
 }

 public function send(UserId $userId, string $title, string $message, array $data = []): bool
 {
  try {
   $user = $this->userRepository->findById($userId);
   if (!$user || !$user->getEmail()) {
    Log::warning("No email found for user {$userId->getValue()}");
    return false;
   }

   return $this->sendEmail($user->getEmail(), $title, $message);
  } catch (\Exception $e) {
   Log::error("Failed to send email to user {$userId->getValue()}: " . $e->getMessage());
   return false;
  }
 }

 public function sendEmail(Email $email, string $subject, string $message, array $attachments = []): bool
 {
  try {
   Mail::send([], [], function ($mail) use ($email, $subject, $message, $attachments) {
    $mail->to($email->getValue())
     ->subject($subject)
     ->html($this->formatMessage($message));

    foreach ($attachments as $attachment) {
     $mail->attach($attachment);
    }
   });

   return true;
  } catch (\Exception $e) {
   Log::error('Email sending exception: ' . $e->getMessage());
   return false;
  }
 }

 public function sendTemplate(Email $email, string $templateName, array $data = []): bool
 {
  try {
   Mail::send($templateName, $data, function ($mail) use ($email, $data) {
    $mail->to($email->getValue())
     ->subject($data['subject'] ?? 'Notification ProSartisan');
   });

   return true;
  } catch (\Exception $e) {
   Log::error('Email template exception: ' . $e->getMessage());
   return false;
  }
 }

 public function getName(): string
 {
  return 'laravel_email';
 }

 public function isAvailable(): bool
 {
  return config('mail.default') !== null;
 }

 private function formatMessage(string $message): string
 {
  return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #2c5aa0;'>ProSartisan</h1>
                </div>
                <div style='background: #f9f9f9; padding: 20px; border-radius: 5px;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                <div style='text-align: center; margin-top: 30px; font-size: 12px; color: #666;'>
                    <p>Ceci est un message automatique de ProSartisan. Merci de ne pas répondre à cet email.</p>
                </div>
            </div>
        </body>
        </html>";
 }
}
