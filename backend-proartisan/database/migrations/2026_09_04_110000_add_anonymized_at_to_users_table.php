<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier C6 (P2-11) — RGPD.
 *
 * Horodatage de l'anonymisation d'un compte (droit à l'effacement). Le compte
 * est conservé pour l'intégrité référentielle des écritures financières et du
 * journal d'audit, mais toutes ses données personnelles sont expurgées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('cgu_accepted_at');
            $table->unsignedBigInteger('anonymized_by')->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['anonymized_at', 'anonymized_by']);
        });
    }
};
