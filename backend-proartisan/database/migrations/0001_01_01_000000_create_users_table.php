<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->enum('role', ['client', 'artisan', 'fournisseur', 'referent', 'admin'])->nullable();
            $table->enum('kyc_status', ['en_attente', 'actif', 'rejete'])->default('en_attente');
            $table->unsignedTinyInteger('score_nzassa')->default(0);
            $table->bigInteger('wallet_materiaux')->default(0);
            $table->bigInteger('wallet_mo')->default(0);
            $table->string('fcm_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Colonne POINT nullable — MySQL 5.7 compatible (pas d'index spatial sur nullable)
        DB::statement('ALTER TABLE users ADD COLUMN position POINT NULL AFTER wallet_mo');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('phone', 20)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS position');
        Schema::dropIfExists('users');
    }
};
