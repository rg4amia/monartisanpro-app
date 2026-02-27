<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JCode extends Model
{
    protected $table = 'jcodes';

    protected $fillable = [
        'mission_id', 'artisan_id', 'fournisseur_id', 'code',
        'qr_url', 'ussd_code', 'montant', 'statut', 'scanned_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'montant'    => 'integer',
            'expires_at' => 'datetime',
            'scanned_at' => 'datetime',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function fournisseur()
    {
        return $this->belongsTo(User::class, 'fournisseur_id');
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif' && $this->expires_at->isFuture();
    }

    public function setPositionScan(float $lat, float $lng): void
    {
        DB::statement(
            'UPDATE jcodes SET position_scan = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
            [$lng, $lat, $this->id]
        );
    }
}
