<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceFingerprint extends Model
{
    use HasFactory;

    protected $table = 'device_fingerprints';

    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_model',
        'app_installation_id',
        'ip_address',
        'ip_subnet',
        'user_agent',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
