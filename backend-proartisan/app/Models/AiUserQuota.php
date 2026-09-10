<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surcharge de quota IA pour un utilisateur mobile donné.
 *
 * L'absence de ligne pour un utilisateur signifie « comportement par défaut »
 * (limite globale `ai_settings.daily_user_limit` / `monthly_user_limit`).
 */
class AiUserQuota extends Model
{
    protected $fillable = [
        'user_id',
        'daily_limit',
        'monthly_limit',
        'blocked',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
