<?php

namespace App\Domain\Shared\ValueObjects;

class NotificationPreferences
{
    private bool $pushEnabled;

    private bool $smsEnabled;

    private bool $whatsappEnabled;

    private bool $emailEnabled;

    private array $channelOrder;

    public function __construct(
        bool $pushEnabled = true,
        bool $smsEnabled = true,
        bool $whatsappEnabled = true,
        bool $emailEnabled = true,
        array $channelOrder = ['push', 'sms', 'whatsapp', 'email']
    ) {
        $this->pushEnabled = $pushEnabled;
        $this->smsEnabled = $smsEnabled;
        $this->whatsappEnabled = $whatsappEnabled;
        $this->emailEnabled = $emailEnabled;
        $this->channelOrder = $channelOrder;
    }

    public function isPushEnabled(): bool
    {
        return $this->pushEnabled;
    }

    public function isSmsEnabled(): bool
    {
        return $this->smsEnabled;
    }

    public function isWhatsappEnabled(): bool
    {
        return $this->whatsappEnabled;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function getChannelOrder(): array
    {
        return $this->channelOrder;
    }

    public function isChannelEnabled(string $channel): bool
    {
        return match ($channel) {
            'push' => $this->pushEnabled,
            'sms' => $this->smsEnabled,
            'whatsapp' => $this->whatsappEnabled,
            'email' => $this->emailEnabled,
            default => false,
        };
    }

    public function toArray(): array
    {
        return [
            'push_enabled' => $this->pushEnabled,
            'sms_enabled' => $this->smsEnabled,
            'whatsapp_enabled' => $this->whatsappEnabled,
            'email_enabled' => $this->emailEnabled,
            'channel_order' => $this->channelOrder,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['push_enabled'] ?? true,
            $data['sms_enabled'] ?? true,
            $data['whatsapp_enabled'] ?? true,
            $data['email_enabled'] ?? true,
            $data['channel_order'] ?? ['push', 'sms', 'whatsapp', 'email']
        );
    }
}
