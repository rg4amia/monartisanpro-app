<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed admin account first
        $this->call(AdminSeeder::class);

        // Seed sectors and trades
        $this->call(TradeSeeder::class);

        // Seed complete platform data for simulation
        $this->call(CompletePlatformSeeder::class);
    }
}
