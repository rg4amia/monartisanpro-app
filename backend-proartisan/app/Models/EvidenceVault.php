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
        'uploaded_by',
        'file_url',
        'sha256_hash',
        'ip_address',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
