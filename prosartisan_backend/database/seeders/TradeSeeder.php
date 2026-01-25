<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = base_path('../Base secteur activié et metier.csv');
        if (! file_exists($csvPath)) {
            $this->command->error("CSV file not found at: $csvPath");

            return;
        }

        $file = fopen($csvPath, 'r');
        // Skip header
        fgetcsv($file, 0, ';');

        while (($data = fgetcsv($file, 0, ';')) !== false) {
            // CSV columns: CODE SECTEUR ACTIVITE;SECTEUR D'ACTIVITE;CODE METIER;METIER
            // Index 1: Sector Name
            // Index 3: Trade Name

            if (! isset($data[1]) || ! isset($data[3])) {
                continue;
            }

            $sectorName = trim($data[1]);
            $tradeName = trim($data[3]);

            if (empty($sectorName) || empty($tradeName)) {
                continue;
            }

            $sector = \App\Models\Sector::firstOrCreate(
                ['name' => $sectorName]
            );

            // Check if trade exists to avoid unique constraint violation in race conditions or seeder re-runs
            $tradeExists = \App\Models\Trade::where('name', $tradeName)
                ->where('sector_id', $sector->id)
                ->exists();

            if (! $tradeExists) {
                try {
                    \App\Models\Trade::create([
                        'name' => $tradeName,
                        'sector_id' => $sector->id,
                    ]);
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }

        fclose($file);
        $this->command->info('Sectors and Trades seeded successfully.');
    }
}
