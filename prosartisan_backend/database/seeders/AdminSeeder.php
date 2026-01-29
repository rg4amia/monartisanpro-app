<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Seed the admin account
     */
    public function run(): void
    {
        $this->command->info('👤 Creating admin account...');

        // Check if admin already exists
        $existingAdmin = DB::table('users')
            ->where('email', 'admin@prosartisan.sn')
            ->first();

        if ($existingAdmin) {
            $this->command->warn('   ⚠️  Admin account already exists');
            $this->command->info('   Email: admin@prosartisan.sn');
            return;
        }

        // Create admin user
        $adminId = Str::uuid()->toString();

        DB::table('users')->insert([
            'id' => $adminId,
            'name' => 'Administrateur ProsArtisan',
            'email' => 'admin@prosartisan.sn',
            'phone' => '+221 77 000 00 00',
            'password' => Hash::make('Admin@2026'),
            'role' => 'ADMIN',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('   ✓ Admin account created successfully!');
        $this->command->info('');
        $this->command->info('   ╔════════════════════════════════════════════╗');
        $this->command->info('   ║        ADMIN CREDENTIALS                   ║');
        $this->command->info('   ╠════════════════════════════════════════════╣');
        $this->command->info('   ║ Email:    admin@prosartisan.sn             ║');
        $this->command->info('   ║ Password: Admin@2026                       ║');
        $this->command->info('   ╚════════════════════════════════════════════╝');
        $this->command->info('');
        $this->command->warn('   ⚠️  IMPORTANT: Change the password after first login!');
    }
}
