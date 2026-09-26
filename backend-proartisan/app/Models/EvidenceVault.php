<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coffre-fort des preuves — garantit l'intégrité cryptographique (SHA-256)
 * de chaque fichier téléversé dans le cadre d'un litige.
 */
class EvidenceVault extends Model
{
    public $timestamps = false;

    protected $table = 'evidence_vault';

    protected $fillable = [
        'litige_id',
        'mission_id',
        'jalon_id',
        'evidence_type',
        'uploaded_by',
        'file_url',
        'file_path',
        'file_size',
        'mime_type',
        'sha256_hash',
        'ip_address',
        'device_fingerprint',
        'gps_lat',
        'gps_lng',
        'is_tampered',
        'tampered_detected_at',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'gps_lat' => 'float',
            'gps_lng' => 'float',
            'is_tampered' => 'boolean',
            'tampered_detected_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function jalon(): BelongsTo
    {
        return $this->belongsTo(Jalon::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
