<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Surcharges de quota IA par utilisateur mobile.
 *
 * La limite globale reste dans `ai_settings` (`daily_user_limit`,
 * `monthly_user_limit`). Cette table ne contient QUE les utilisateurs pour
 * lesquels un·e admin a défini une exception (limite spécifique, blocage,
 * note). Absence de ligne = comportement par défaut (limite globale).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_user_quotas')) {
            Schema::create('ai_user_quotas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                // NULL = utilise la limite globale ; 0 = illimité pour cet utilisateur.
                $table->unsignedInteger('daily_limit')->nullable();
                $table->unsignedInteger('monthly_limit')->nullable();
                $table->boolean('blocked')->default(false);
                $table->string('note', 500)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // Réglage global : plafond mensuel par utilisateur (0 = illimité).
        if (Schema::hasTable('ai_settings')) {
            DB::table('ai_settings')->updateOrInsert(
                ['key' => 'monthly_user_limit'],
                [
                    'value' => '0',
                    'description' => "Nombre maximum d'appels IA par mois et par utilisateur (0 = illimité).",
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_user_quotas');
        DB::table('ai_settings')->where('key', 'monthly_user_limit')->delete();
    }
};
