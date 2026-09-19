<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet d'adresses de livraison du client (checkout matériaux).
 *
 * MySQL 5.7 : `position` est un POINT sans SRID (ajouté via DB::statement,
 * cf. CLAUDE.md règle MySQL) — nullable, une adresse peut n'être qu'un texte
 * libre sans GPS précis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('recipient_name', 150);
            $table->string('recipient_phone', 20);
            $table->string('address_line', 255);
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->default("Côte d'Ivoire");
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE addresses ADD COLUMN position POINT NULL AFTER country');
        } else {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('position')->nullable(); // Simple string pour les tests SQLite
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
