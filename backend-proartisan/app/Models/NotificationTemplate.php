<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surcharge, par un administrateur, des textes et canaux d'un événement du
 * catalogue de notifications. Toute colonne NULL reprend la valeur d'origine
 * du code (`App\Services\Notifications\NotificationCatalog`).
 */
class NotificationTemplate extends Model
{
    protected $fillable = [
        'event_key',
        'push_title',
        'push_body',
        'sms_body',
        'channel_in_app',
        'channel_push',
        'channel_sms',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_in_app' => 'boolean',
            'channel_push' => 'boolean',
            'channel_sms' => 'boolean',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
