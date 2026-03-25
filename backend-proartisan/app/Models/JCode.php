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
        'photo_materiaux_url', 'photo_latitude', 'photo_longitude', 'photo_taken_at',
        'paiement_status', 'paye_at'
    ];

    protected $hidden = ['position_scan'];

    protected $casts = [
        'montant'    => 'integer',
        'expires_at' => 'datetime',
        'scanned_at' => 'datetime',
        'photo_latitude' => 'float',
        'photo_longitude' => 'float',
        'photo_taken_at' => 'datetime',
        'paye_at' => 'datetime',
    ];

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

    public function items()
    {
        return $this->hasMany(JCodeItem::class, 'jcode_id')->orderBy('id');
    }

    public function isActif(): bool
    {
        return $this->statut === 'actif' && $this->expires_at->isFuture();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            return $this->newQuery()->whereKey((int) $value)->firstOrFail();
        }

        $normalized = strtoupper(trim((string) $value));

        return $this->newQuery()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->firstOrFail();
    }

    public function setPositionScan(float $lat, float $lng): void
    {
        if (config('database.default') === 'sqlite') {
            $this->update(['position_scan' => "$lat,$lng"]);
            return;
        }

        DB::statement(
            "UPDATE jcodes SET position_scan = ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')'), 4326) WHERE id = ?",
            [$lng, $lat, $this->id]
        );
    }
}
