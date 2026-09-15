<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séquestre d'accès aux candidatures : avant de consulter la liste des
 * postulants à son offre, le recruteur doit payer un séquestre estimé
 * (taux journalier saisi × durée de l'offre) — ce verrou financier est la
 * principale protection anti-contournement de la plateforme, le numéro de
 * l'artisan n'étant lui-même jamais exposé au recruteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->foreignId('applicants_escrow_transaction_id')->nullable()->after('metadata')
                ->constrained('transactions')->nullOnDelete();
            $table->dateTime('applicants_unlocked_at')->nullable()->after('applicants_escrow_transaction_id');
            $table->boolean('applicants_escrow_reserved')->default(false)->after('applicants_unlocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applicants_escrow_transaction_id');
            $table->dropColumn(['applicants_unlocked_at', 'applicants_escrow_reserved']);
        });
    }
};
