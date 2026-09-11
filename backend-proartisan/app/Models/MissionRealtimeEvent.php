<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionRealtimeEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'mission_id',
        'event_type',
        'payload_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }
}
