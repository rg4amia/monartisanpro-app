<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FournisseurAgree extends Model
{
    protected $table = 'fournisseurs_agrees';

    protected $fillable = [
        'user_id', 'nom_boutique', 'position', 'statut', 'approuve_at',
    ];

    protected $hidden = ['position'];

    protected $appends = ['coordinates'];

    protected function casts(): array
    {
        return [
            'approuve_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(SupplierProduct::class, 'supplier_id', 'user_id');
    }

    public function getCoordinatesAttribute(): ?array
    {
        return $this->getPositionCoords();
    }

    public function setPosition(float $lat, float $lng): void
    {
        if (config('database.default') === 'sqlite') {
            $this->update(['position' => "$lat,$lng"]);
            return;
        }

        DB::statement(
            "UPDATE fournisseurs_agrees SET position = ST_SRID(POINT(?, ?), 4326) WHERE id = ?",
            [$lng, $lat, $this->id]
        );
    }

    public function getPositionCoords(): ?array
    {
        if (config('database.default') === 'sqlite') {
            if (! is_string($this->position) || ! str_contains($this->position, ',')) {
                return null;
            }

            [$lat, $lng] = array_map('trim', explode(',', $this->position, 2));

            return [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        $row = DB::selectOne(
            'SELECT ST_X(position) as lng, ST_Y(position) as lat FROM fournisseurs_agrees WHERE id = ?',
            [$this->id]
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }
}
