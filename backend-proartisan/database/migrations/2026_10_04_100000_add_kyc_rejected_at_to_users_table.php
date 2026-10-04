<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 27, lot C — date du rejet d'un dossier KYC par un administrateur.
 *
 * Un utilisateur rejeté qui renvoie ses pièces revient « en attente » : sans
 * cette date, plus rien ne distinguait son dossier d'un dossier neuf, et
 * l'analyse automatique aurait pu réactiver un compte qu'un administrateur a
 * rejeté (Règle d'or 69). Idempotente (Règle d'or 55).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'kyc_rejected_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('kyc_rejected_at')->nullable();
            });
        }

        // Dossiers déjà rejetés : la date exacte n'a pas été conservée, celle
        // de la dernière modification du compte en tient lieu.
        DB::table('users')
            ->where('kyc_status', 'rejete')
            ->whereNull('kyc_rejected_at')
            ->update(['kyc_rejected_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'kyc_rejected_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('kyc_rejected_at');
            });
        }
    }
};
