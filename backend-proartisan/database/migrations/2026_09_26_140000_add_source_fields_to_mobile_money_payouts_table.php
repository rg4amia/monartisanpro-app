<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remboursement client après litige : le virement part vers le client, mais
 * les fonds sont prélevés sur les portefeuilles de l'artisan, souvent sur les
 * deux (matériaux et main-d'œuvre) en un seul virement.
 *
 * - `source_user_id` : titulaire du portefeuille débité (null = le bénéficiaire) ;
 * - `debits` : ventilation du débit par portefeuille (null = `wallet_type` / `montant`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_money_payouts')) {
            return;
        }

        if (! Schema::hasColumn('mobile_money_payouts', 'source_user_id')) {
            Schema::table('mobile_money_payouts', function (Blueprint $table) {
                $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('mobile_money_payouts', 'debits')) {
            Schema::table('mobile_money_payouts', function (Blueprint $table) {
                $table->json('debits')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mobile_money_payouts', 'source_user_id')) {
            Schema::table('mobile_money_payouts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('source_user_id');
            });
        }

        if (Schema::hasColumn('mobile_money_payouts', 'debits')) {
            Schema::table('mobile_money_payouts', function (Blueprint $table) {
                $table->dropColumn('debits');
            });
        }
    }
};
