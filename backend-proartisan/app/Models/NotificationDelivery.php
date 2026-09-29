<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Journal des envois push et SMS : une ligne par tentative, jamais modifiée.
 * Seules la purge et l'anonymisation RGPD suppriment des lignes.
 */
class NotificationDelivery extends Model
{
    public const UPDATED_AT = null;

    public const CHANNEL_PUSH = 'push';

    public const CHANNEL_SMS = 'sms';

    public const STATUS_SENT = 'envoye';

    public const STATUS_FAILED = 'echoue';

    public const STATUS_SKIPPED = 'ignore';

    protected $fillable = [
        'notification_id',
        'user_id',
        'event_key',
        'channel',
        'provider',
        'status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Le journal des envois de notifications est en ajout seul.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
