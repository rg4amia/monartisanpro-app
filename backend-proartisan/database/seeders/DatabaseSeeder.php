<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SectorSeeder::class, // Secteurs + Métiers en premier
            UserSeeder::class,   // Users de test
        ]);
    }
}
