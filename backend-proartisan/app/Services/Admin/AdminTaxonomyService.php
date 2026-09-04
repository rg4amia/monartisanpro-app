<?php

namespace App\Services\Admin;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des catégories (secteurs) et sous-catégories (métiers) depuis le backoffice.
 */
class AdminTaxonomyService
{
    public function __construct(private AdminActivityLogger $audit) {}

    public function createSector(string $name): Sector
    {
        $sector = Sector::create(['name' => $name]);

        $this->audit->log('sector.created', $sector);

        return $sector;
    }

    public function updateSector(Sector $sector, string $name): Sector
    {
        $sector->update(['name' => $name]);

        $this->audit->log('sector.updated', $sector);

        return $sector;
    }

    /**
     * @param  array{sector_id: int|string, name: string}  $data
     */
    public function createTrade(array $data): Trade
    {
        $exists = Trade::where('sector_id', $data['sector_id'])
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Cette sous-catégorie existe déjà dans cette catégorie.',
            ]);
        }

        $trade = Trade::create([
            'sector_id' => $data['sector_id'],
            'name' => $data['name'],
        ]);

        $this->audit->log('trade.created', $trade, ['sector_id' => $trade->sector_id]);

        return $trade;
    }

    public function updateTrade(Trade $trade, string $name): Trade
    {
        $trade->update(['name' => $name]);

        $this->audit->log('trade.updated', $trade, ['sector_id' => $trade->sector_id]);

        return $trade;
    }
}
