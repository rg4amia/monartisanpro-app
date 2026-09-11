<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'sender_id',
        'type',
        'content',
        'media_url',
        'media_metadata',
        'is_redacted',
        'flagged_for_review',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'media_metadata' => 'array',
            'is_redacted' => 'boolean',
            'flagged_for_review' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
