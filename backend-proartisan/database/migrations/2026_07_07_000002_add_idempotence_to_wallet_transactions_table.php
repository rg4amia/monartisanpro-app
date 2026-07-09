<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une clé d'idempotence sur wallet_transactions (Epic 10 — Ledger Immuable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('cle_idempotence', 100)->nullable()->after('reference');
        });

        // Remplir les lignes existantes avec une clé générée compatible SQLite / MySQL
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE wallet_transactions
                SET cle_idempotence = 'legacy-' || id
                WHERE cle_idempotence IS NULL
            ");
        } else {
            DB::statement("
                UPDATE wallet_transactions
                SET cle_idempotence = CONCAT('legacy-', id)
                WHERE cle_idempotence IS NULL
            ");
        }

        // Rendre la colonne NOT NULL et ajouter l'index unique
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('cle_idempotence', 100)->nullable(false)->change();
            $table->unique(['operation', 'cle_idempotence'], 'idx_wallet_idempotence');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('idx_wallet_idempotence');
            $table->dropColumn('cle_idempotence');
        });
    }
};
