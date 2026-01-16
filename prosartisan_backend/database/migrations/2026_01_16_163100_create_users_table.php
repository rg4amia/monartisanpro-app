<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 /**
  * Run the migrations.
  *
  * Creates the users table for storing all user types
  * (Client, Artisan, Fournisseur, ReferentZone, Admin)
  */
 public function up(): void
 {
  Schema::create('users', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->string('email', 255)->unique();
   $table->string('password_hash', 255);
   $table->string('user_type', 50); // CLIENT, ARTISAN, FOURNISSEUR, REFERENT_ZONE, ADMIN
   $table->string('account_status', 50)->default('PENDING'); // PENDING, ACTIVE, SUSPENDED
   $table->string('phone_number', 20)->nullable();
   $table->integer('failed_login_attempts')->default(0);
   $table->timestamp('locked_until')->nullable();
   $table->timestamps();

   // Indexes
   $table->index('email');
   $table->index('user_type');
   $table->index('account_status');
  });
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('users');
 }
};
