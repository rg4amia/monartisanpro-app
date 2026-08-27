<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devis extends Model
{
    protected $fillable = [
        'mission_id', 'artisan_id', 'lignes_json', 'jalons_json', 'statut', 'ratio_materiaux',
        'materials_required', 'intervention_type_id', 'commission_service_ratio',
        'is_avenant', 'parent_devis_id',
    ];

    protected function casts(): array
    {
        return [
            'lignes_json'  => 'array',
            'jalons_json'  => 'array',
            'materials_required' => 'boolean',
            'intervention_type_id' => 'integer',
            'commission_service_ratio' => 'float',
            'is_avenant' => 'boolean',
            'parent_devis_id' => 'integer',
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

    public function interventionType()
    {
        return $this->belongsTo(InterventionType::class);
    }

    public function parentDevis()
    {
        return $this->belongsTo(Devis::class, 'parent_devis_id');
    }

    public function avenants()
    {
        return $this->hasMany(Devis::class, 'parent_devis_id');
    }

    public function getMontantTotalAttribute(): int
    {
        return $this->montant_materiaux + $this->montant_mo;
    }

    public function getMontantMateriauxAttribute(): int
    {
        $platformFeeRatio = \App\Models\Setting::getValueByKey('platform_fee_ratio', 0.03);
        $artisanStockFeeRatio = \App\Models\Setting::getValueByKey('commission_artisan_stock', 0.05);

        $total = 0;
        foreach ($this->lignes_json ?? [] as $ligne) {
            if (($ligne['type'] ?? '') === 'mat') {
                $isArtisanStock = ($ligne['source'] ?? '') === 'artisan_stock' || !empty($ligne['artisan_stock_id']);
                $ratio = $isArtisanStock ? $artisanStockFeeRatio : $platformFeeRatio;
                $total += (int) round(($ligne['montant'] ?? 0) * (1 + $ratio));
            }
        }
        return $total;
    }

    public function getMontantMoAttribute(): int
    {
        if ($this->commission_service_ratio !== null) {
            $commissionService = (float) $this->commission_service_ratio;
        } else {
            $commissionService = $this->artisan 
                ? \App\Models\Setting::getLaborCommissionForArtisan($this->artisan)
                : \App\Models\Setting::getValueByKey('commission_service', 0.10);
        }

        $rawMo = collect($this->lignes_json ?? [])
            ->where('type', 'mo')
            ->sum('montant');
        return (int) round($rawMo * (1 + $commissionService));
    }
}
