<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    protected $fillable = [
        'dispute_id',
        'user_id',
        'message',
        'attachments',
        'is_admin_message',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_admin_message' => 'boolean',
        'read_at' => 'datetime',
    ];

    // Relationships
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
