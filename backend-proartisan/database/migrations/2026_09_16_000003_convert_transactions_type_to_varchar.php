<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `transactions.type` était un ENUM strict limité aux 5 valeurs d'origine
 * (acompte, liberation_jalon, paiement_fournisseur, remboursement, credit).
 * Le module de recrutement introduit un nouveau type de transaction
 * (`recruitment_escrow`) : plutôt que d'étendre indéfiniment l'ENUM à chaque
 * nouveau domaine métier, on suit le précédent déjà posé pour
 * `missions.status` (migration 2026_07_23_212000) et on convertit la colonne
 * en VARCHAR. MySQL 5.7 ne supporte pas `->change()` proprement sur un ENUM
 * (nécessite doctrine/dbal) : on utilise le SQL brut. SQLite applique bien la
 * contrainte ENUM sous forme de CHECK — recréer la colonne via `->change()`
 * (natif Laravel 11+, sans doctrine/dbal) est nécessaire pour les tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('type', 50)->change();
            });

            return;
        }

        DB::statement('
            ALTER TABLE transactions
            MODIFY COLUMN type VARCHAR(50) NOT NULL
        ');
    }

    public function down(): void
    {
        // Pas de réversion nécessaire.
    }
};
