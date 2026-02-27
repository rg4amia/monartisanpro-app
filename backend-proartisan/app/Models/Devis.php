<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devis extends Model
{
    protected $fillable = [
        'mission_id', 'artisan_id', 'lignes_json', 'jalons_json', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'lignes_json'  => 'array',
            'jalons_json'  => 'array',
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

    public function getMontantTotalAttribute(): int
    {
        return collect($this->lignes_json ?? [])->sum('montant');
    }

    public function getMontantMateriauxAttribute(): int
    {
        return collect($this->lignes_json ?? [])
            ->where('type', 'mat')
            ->sum('montant');
    }

    public function getMontantMoAttribute(): int
    {
        return collect($this->lignes_json ?? [])
            ->where('type', 'mo')
            ->sum('montant');
    }
}
