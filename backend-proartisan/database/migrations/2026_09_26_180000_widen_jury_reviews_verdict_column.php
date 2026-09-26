<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 12 — `jury_reviews.verdict` était un ENUM à deux valeurs
 * (`CONFORME`, `NON_CONFORME`) alors que l'API propose et valide aussi
 * `RESPONSABILITE_PARTAGEE` : ce vote échouait en erreur 500 (« Data
 * truncated » sous MariaDB strict, contrainte CHECK sous SQLite). La liste
 * des verdicts est tenue par `LitigeService::submitJuryVote`.
 *
 * Idempotente (Règle d'or 55) : réappliquer `change()` sur une colonne déjà
 * en chaîne ne modifie rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jury_reviews') || ! Schema::hasColumn('jury_reviews', 'verdict')) {
            return;
        }

        Schema::table('jury_reviews', function (Blueprint $table) {
            $table->string('verdict', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Aucun retour à l'ENUM : il rejetterait les votes
        // `RESPONSABILITE_PARTAGEE` déjà enregistrés.
    }
};
