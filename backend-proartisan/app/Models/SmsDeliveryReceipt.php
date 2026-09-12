<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Accusé de réception d'un SMS envoyé via l'API SMSpro.
 */
class SmsDeliveryReceipt extends Model
{
    /**
     * Statuts signifiant que le message n'atteindra pas le destinataire.
     *
     * `Enroute` et `Accepted` sont transitoires, `Delivered` est le succès :
     * aucun des trois n'est un échec.
     */
    public const FAILURE_STATUSES = [
        'Undelivered',
        'Expired',
        'Rejected',
        'Failed',
    ];

    protected $fillable = [
        'uid',
        'message_id',
        'recipient',
        'sender_id',
        'sms_type',
        'status',
        'campaign_id',
        'status_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'status_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', self::FAILURE_STATUSES);
    }

    public function scopeOtp(Builder $query): Builder
    {
        return $query->where('sms_type', 'otp');
    }

    public function isFailure(): bool
    {
        return in_array($this->status, self::FAILURE_STATUSES, true);
    }
}
