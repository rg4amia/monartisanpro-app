<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jalon extends Model
{
    protected $fillable = [
        'mission_id', 'ordre', 'description', 'montant', 'statut',
        'otp_code', 'otp_expires_at', 'photos_json', 'valide_at', 'paye_at',
    ];

    protected function casts(): array
    {
        return [
            'montant'        => 'integer',
            'photos_json'    => 'array',
            'otp_expires_at' => 'datetime',
            'valide_at'      => 'datetime',
            'paye_at'        => 'datetime',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function isOtpValid(string $code): bool
    {
        return $this->otp_code === $code
            && $this->otp_expires_at
            && $this->otp_expires_at->isFuture();
    }
}
