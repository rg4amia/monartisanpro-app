<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit des actions administrateur (Chantier C3 / P0-4).
 *
 * Table append-only : pas de `updated_at`, pas de mutation applicative.
 */
class AdminActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'admin_name',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'context',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
