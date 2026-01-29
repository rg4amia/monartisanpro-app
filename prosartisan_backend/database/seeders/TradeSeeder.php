<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\Trade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds sectors and trades from CSV file containing 142 trade records
     * across multiple activity sectors
     */
    public function run(): void
    {
        $csvPath = public_path('base_secteur_activite_metier.csv');
        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found at: $csvPath");
            return;
        }

        $this->command->info('🌱 Seeding sectors and trades from CSV...');

        DB::beginTransaction();

        try {
            $file = fopen($csvPath, 'r');

            // Skip header row
            fgetcsv($file, 0, ';');

            $sectorsCache = [];
            $tradesData = [];
            $sectorCount = 0;
            $tradeCount = 0;

            // Read and prepare data
            while (($data = fgetcsv($file, 0, ';')) !== false) {
                // CSV columns: CODE SECTEUR ACTIVITE;SECTEUR D'ACTIVITE;CODE METIER;METIER
                // Index 0: Sector Code
                // Index 1: Sector Name
                // Index 2: Trade Code
                // Index 3: Trade Name

                if (!isset($data[1]) || !isset($data[3])) {
                    continue;
                }

                $sectorCode = isset($data[0]) ? trim($data[0]) : null;
                $sectorName = trim($data[1]);
                $tradeCode = isset($data[2]) ? trim($data[2]) : null;
                $tradeName = trim($data[3]);

                if (empty($sectorName) || empty($tradeName)) {
                    continue;
                }

                // Cache sector to avoid repeated queries
                if (!isset($sectorsCache[$sectorName])) {
                    $sector = Sector::firstOrCreate(
                        ['name' => $sectorName],
                        ['code' => $sectorCode]
                    );
                    $sectorsCache[$sectorName] = $sector->id;
                    $sectorCount++;
                }

                $sectorId = $sectorsCache[$sectorName];

                // Prepare trade data for batch insert
                $tradesData[] = [
                    'name' => $tradeName,
                    'code' => $tradeCode,
                    'sector_id' => $sectorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            fclose($file);

            // Batch insert trades (more efficient than individual inserts)
            if (!empty($tradesData)) {
                // Remove duplicates based on name and sector_id
                $uniqueTrades = [];
                $seen = [];

                foreach ($tradesData as $trade) {
                    $key = $trade['name'] . '|' . $trade['sector_id'];
                    if (!isset($seen[$key])) {
                        $uniqueTrades[] = $trade;
                        $seen[$key] = true;
                    }
                }

                // Insert in chunks to avoid memory issues
                $chunks = array_chunk($uniqueTrades, 50);
                foreach ($chunks as $chunk) {
                    try {
                        Trade::insert($chunk);
                        $tradeCount += count($chunk);
                    } catch (\Exception $e) {
                        // If batch insert fails, try individual inserts
                        foreach ($chunk as $tradeData) {
                            try {
                                Trade::create($tradeData);
                                $tradeCount++;
                            } catch (\Exception $e) {
                                // Skip duplicates
                                continue;
                            }
                        }
                    }
                }
            }

            DB::commit();

            $this->command->info("   ✓ Created {$sectorCount} sectors");
            $this->command->info("   ✓ Created {$tradeCount} trades");
            $this->command->info('✅ Sectors and Trades seeded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
