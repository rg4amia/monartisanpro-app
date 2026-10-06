<?php

namespace App\Models;

use App\Casts\PrivateMediaPayload;
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
            'payload_json' => PrivateMediaPayload::class,
            'created_at' => 'datetime',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }
}
