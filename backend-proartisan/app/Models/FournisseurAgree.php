<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FournisseurAgree extends Model
{
    protected $table = 'fournisseurs_agrees';

    protected $fillable = [
        'user_id', 'nom_boutique', 'statut', 'approuve_at',
    ];

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

    public function setPosition(float $lat, float $lng): void
    {
        DB::statement(
            'UPDATE fournisseurs_agrees SET position = POINT(?, ?) WHERE id = ?',
            [$lng, $lat, $this->id]
        );
    }

    public function getPositionCoords(): ?array
    {
        $row = DB::selectOne(
            'SELECT ST_X(position) as lng, ST_Y(position) as lat FROM fournisseurs_agrees WHERE id = ?',
            [$this->id]
        );

        return $row ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }
}
