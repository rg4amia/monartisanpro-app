<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devis extends Model
{
    protected $fillable = [
        'mission_id', 'artisan_id', 'lignes_json', 'jalons_json', 'statut', 'ratio_materiaux',
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
        return $this->montant_materiaux + $this->montant_mo;
    }

    public function getMontantMateriauxAttribute(): int
    {
        $platformFeeRatio = \App\Models\Setting::getValueByKey('platform_fee_ratio', 0.03);
        $rawMat = collect($this->lignes_json ?? [])
            ->where('type', 'mat')
            ->sum('montant');
        return (int) round($rawMat * (1 + $platformFeeRatio));
    }

    public function getMontantMoAttribute(): int
    {
        $commissionService = \App\Models\Setting::getValueByKey('commission_service', 0.10);
        $rawMo = collect($this->lignes_json ?? [])
            ->where('type', 'mo')
            ->sum('montant');
        return (int) round($rawMo * (1 + $commissionService));
    }
}
