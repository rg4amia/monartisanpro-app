<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'admin:create
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--phone= : Admin phone number}';

 /**
  * The console command description.
  *
  * @var string
  */
 protected $description = 'Create a new admin account';

 /**
  * Execute the console command.
  */
 public function handle()
 {
  $this->info('╔════════════════════════════════════════════╗');
  $this->info('║     ProsArtisan Admin Account Creator     ║');
  $this->info('╚════════════════════════════════════════════╝');
  $this->newLine();

  // Get or ask for email
  $email = $this->option('email') ?: $this->ask('Admin email address', 'admin@prosartisan.sn');

  // Validate email
  $validator = Validator::make(['email' => $email], [
   'email' => 'required|email',
  ]);

  if ($validator->fails()) {
   $this->error('Invalid email address');
   return 1;
  }

  // Check if email already exists
  $existingUser = DB::table('users')->where('email', $email)->first();
  if ($existingUser) {
   $this->error("User with email {$email} already exists!");

   if ($this->confirm('Do you want to update this user to admin role?')) {
    DB::table('users')
     ->where('email', $email)
     ->update([
      'user_type' => 'ADMIN',
      'account_status' => 'ACTIVE',
      'updated_at' => now(),
     ]);

    $this->info('✓ User updated to admin role successfully!');
    return 0;
   }

   return 1;
  }

  // Get or ask for phone
  $phone = $this->option('phone') ?: $this->ask('Admin phone number', '+221 77 000 00 00');

  // Get or ask for password
  $password = $this->option('password');
  if (!$password) {
   $password = $this->secret('Admin password (min 8 characters)');
   $passwordConfirm = $this->secret('Confirm password');

   if ($password !== $passwordConfirm) {
    $this->error('Passwords do not match!');
    return 1;
   }
  }

  // Validate password
  if (strlen($password) < 8) {
   $this->error('Password must be at least 8 characters long');
   return 1;
  }

  // Create admin user
  try {
   $adminId = Str::uuid()->toString();

   DB::table('users')->insert([
    'id' => $adminId,
    'email' => $email,
    'password_hash' => Hash::make($password),
    'user_type' => 'ADMIN',
    'account_status' => 'ACTIVE',
    'phone_number' => $phone,
    'created_at' => now(),
    'updated_at' => now(),
   ]);

   $this->newLine();
   $this->info('✅ Admin account created successfully!');
   $this->newLine();
   $this->info('╔════════════════════════════════════════════╗');
   $this->info('║           ADMIN CREDENTIALS                ║');
   $this->info('╠════════════════════════════════════════════╣');
   $this->info("║ Email:    {$email}");
   $this->info("║ Phone:    {$phone}");
   $this->info("║ Password: " . str_repeat('*', strlen($password)));
   $this->info('╚════════════════════════════════════════════╝');
   $this->newLine();
   $this->warn('⚠️  IMPORTANT: Keep these credentials secure!');

   return 0;
  } catch (\Exception $e) {
   $this->error('Failed to create admin account: ' . $e->getMessage());
   return 1;
  }
 }
}
