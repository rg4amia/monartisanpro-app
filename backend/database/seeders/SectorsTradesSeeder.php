<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Seeder;

class SectorsTradesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = base_path('../cahier_charge/base_secteur_activite_metier.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');

        // Skip header row
        fgetcsv($file, 0, ';');

        $sectorsData = [];
        $tradesData = [];

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            // Clean BOM and whitespace
            $sectorCode = trim($row[0], "\xEF\xBB\xBF ");
            $sectorName = trim($row[1]);
            $tradeCode = trim($row[2]);
            $tradeName = trim($row[3]);

            // Store unique sectors
            if (!isset($sectorsData[$sectorCode])) {
                $sectorsData[$sectorCode] = [
                    'code' => $sectorCode,
                    'name' => $sectorName,
                ];
            }

            // Store trade data
            $tradesData[] = [
                'sector_code' => $sectorCode,
                'code' => $tradeCode,
                'name' => $tradeName,
            ];
        }

        fclose($file);

        // Create or update sectors
        foreach ($sectorsData as $sectorData) {
            Sector::updateOrCreate(
                ['code' => $sectorData['code']],
                ['name' => $sectorData['name']]
            );
        }

        // Create or update trades
        foreach ($tradesData as $tradeData) {
            $sector = Sector::where('code', $tradeData['sector_code'])->first();

            if ($sector) {
                Trade::updateOrCreate(
                    ['code' => $tradeData['code']],
                    [
                        'sector_id' => $sector->id,
                        'name' => $tradeData['name'],
                    ]
                );
            }
        }

        $this->command->info('Successfully seeded ' . count($sectorsData) . ' sectors and ' . count($tradesData) . ' trades.');
    }
}
