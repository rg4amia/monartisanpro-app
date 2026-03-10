<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CommuneSeeder::class, // Communes Abidjan (géographie)
            SectorSeeder::class, // Secteurs + Métiers en premier
            UserSeeder::class,   // Users de test
        ]);
    }
}
