<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 27 — preuve qu'un code a été validé pour un numéro. `used_at` ne
 * suffit pas : il est aussi posé quand un code est brûlé après trop d'essais.
 * L'inscription exige cette preuve (Règle d'or 98). Idempotente (Règle d'or 55).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('otps') || Schema::hasColumn('otps', 'verified_at')) {
            return;
        }

        Schema::table('otps', function (Blueprint $table) {
            $table->dateTime('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('otps') && Schema::hasColumn('otps', 'verified_at')) {
            Schema::table('otps', function (Blueprint $table) {
                $table->dropColumn('verified_at');
            });
        }
    }
};
