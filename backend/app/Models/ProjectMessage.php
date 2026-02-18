<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMessage extends Model
{
    protected $fillable = [
        'project_id',
        'sender_id',
        'message',
        'attachments',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Helper methods
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isReadBy(int $userId): bool
    {
        return $this->read_at !== null && $this->sender_id !== $userId;
    }
}
