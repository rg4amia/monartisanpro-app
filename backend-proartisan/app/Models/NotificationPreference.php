<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Préférences de notification d'un utilisateur : accord aux messages
 * promotionnels (push, désactivé par défaut — une campagne promotionnelle ne
 * vise que les comptes qui l'ont donné) et rubriques du catalogue dont il a
 * coupé le push ou le SMS (Chantier 14, lot E).
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'promotional_push', 'promotional_push_at',
        'muted_push_domains', 'muted_sms_domains',
    ];

    protected $attributes = ['promotional_push' => false];

    protected function casts(): array
    {
        return [
            'promotional_push' => 'boolean',
            'promotional_push_at' => 'datetime',
            'muted_push_domains' => 'array',
            'muted_sms_domains' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
