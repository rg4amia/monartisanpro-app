<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 12 — score ProsArtisan minimal d'un juré, réglable depuis le
 * backoffice (onglet Paramètres, modification auditée) : l'administrateur
 * l'abaisse pour élargir le vivier de jurés quand un métier en compte trop
 * peu. Idempotente (Règle d'or 55).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || DB::table('settings')->where('key', 'jury_min_score')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'jury_min_score',
            'value' => '800',
            'type' => 'integer',
            'group' => 'litiges',
            'label' => 'Score ProsArtisan minimal d\'un juré (0 à 1000)',
            'description' => "Seuls les artisans du même métier atteignant ce score, recalculé depuis leur historique, siègent au Jury ProsArtisan. L'abaisser élargit le vivier quand un jury ne peut être réuni.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'jury_min_score')->delete();
        }
    }
};
