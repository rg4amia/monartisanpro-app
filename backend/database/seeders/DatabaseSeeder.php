<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

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

        $this->call([
            SectorsTradesSeeder::class,
            BigDataSeeder::class
            // Uncomment one of the following:
            // TestDataSeeder::class,  // Small dataset for testing
            // BigDataSeeder::class,   // Large dataset for performance testing
        ]);
    }
}
