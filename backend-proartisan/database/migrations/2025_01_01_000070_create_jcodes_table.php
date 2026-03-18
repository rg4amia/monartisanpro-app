<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained();
            $table->foreignId('artisan_id')->constrained('users');
            $table->foreignId('fournisseur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 7)->unique();
            $table->text('qr_url')->nullable();
            $table->string('ussd_code', 20)->nullable();
            $table->bigInteger('montant');
            $table->enum('statut', ['actif', 'utilise', 'expire'])->default('actif');
            $table->timestamp('scanned_at')->nullable();
            $table->dateTime('expires_at'); // dateTime évite les restrictions strict_mode MySQL 5.7
            $table->timestamps();
        });

        // position_scan nullable → pas d'index spatial (MySQL 5.7 : SRID non supporté dans ALTER TABLE)
        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE jcodes ADD COLUMN position_scan POINT NULL AFTER statut');
        } else {
            Schema::table('jcodes', function (Blueprint $table) {
                $table->string('position_scan')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jcodes');
    }
};
