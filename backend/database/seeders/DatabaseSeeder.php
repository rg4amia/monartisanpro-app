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
        // Create admin user for Filament
        User::firstOrCreate(
            ['email' => 'admin@prosartisan.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'kyc_status' => 'approved',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Seed sectors and trades from CSV
        $this->call([
            SectorsTradesSeeder::class,
            TestDataSeeder::class,
        ]);
    }
}
