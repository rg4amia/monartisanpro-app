<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Force explicit DEFAULT 0 on users.score_prosartisan via native DDL
        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE users MODIFY COLUMN score_prosartisan INT UNSIGNED NOT NULL DEFAULT 0');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('score_prosartisan')->default(0)->change();
            });
        }

        // 2. Nettoyage rétroactif : tout compte au score non nul sans aucune évaluation ni écriture au ledger
        // (ex: comptes créés sous l'ancien DEFAULT 300) est remis à 0, en respectant les comptes dont le score est gelé.
        DB::statement('
            UPDATE users 
            SET score_prosartisan = 0 
            WHERE score_frozen = 0 
              AND score_prosartisan > 0 
              AND id NOT IN (SELECT evalue_id FROM evaluations WHERE evalue_id IS NOT NULL)
              AND id NOT IN (SELECT user_id FROM score_ledger_entries WHERE user_id IS NOT NULL)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de retour arrière nécessaire : le score sans historique doit toujours rester à 0.
    }
};
