<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        $sectors = [];
        $trades = [];
        $currentSectorId = null;

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            // Clean BOM and whitespace
            $sectorCode = trim($row[0], "\xEF\xBB\xBF ");
            $sectorName = trim($row[1]);
            $tradeCode = trim($row[2]);
            $tradeName = trim($row[3]);

            // Create sector if not already exists
            if (!isset($sectors[$sectorCode])) {
                $sectors[$sectorCode] = [
                    'code' => $sectorCode,
                    'name' => $sectorName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Store trade data (will insert after sectors)
            $trades[] = [
                'sector_code' => $sectorCode,
                'code' => $tradeCode,
                'name' => $tradeName,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($file);

        // Insert sectors first
        DB::table('sectors')->insert(array_values($sectors));

        // Get sector IDs mapping
        $sectorMapping = DB::table('sectors')
            ->select('id', 'code')
            ->get()
            ->keyBy('code');

        // Map trades to sector IDs
        $tradesWithSectorIds = array_map(function ($trade) use ($sectorMapping) {
            $sectorId = $sectorMapping[$trade['sector_code']]->id;
            unset($trade['sector_code']);
            $trade['sector_id'] = $sectorId;
            return $trade;
        }, $trades);

        // Insert trades
        DB::table('trades')->insert($tradesWithSectorIds);

        $this->command->info('Successfully seeded ' . count($sectors) . ' sectors and ' . count($trades) . ' trades.');
    }
}
