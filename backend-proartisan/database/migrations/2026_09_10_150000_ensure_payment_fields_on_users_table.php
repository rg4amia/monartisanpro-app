<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filet de sécurité : en production, la migration
 * 2026_08_23_162000_add_payment_fields_to_users_table a été enregistrée comme
 * exécutée alors que les colonnes n'existent pas réellement (échec partiel /
 * base restaurée depuis un dump antérieur). Résultat : tout `users->update()`
 * touchant `payment_phone` renvoie une 500 (« Unknown column 'payment_phone' »),
 * ce qui casse l'enregistrement du numéro Mobile Money côté client ET la
 * soumission de devis côté artisan.
 *
 * Cette migration ré-ajoute les colonnes de façon idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'payment_phone')) {
                $table->string('payment_phone', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'preferred_payment_provider')) {
                $table->string('preferred_payment_provider', 20)->nullable()->after('payment_phone');
            }
        });
    }

    public function down(): void
    {
        // Volontairement sans effet : les colonnes appartiennent à la migration
        // d'origine (2026_08_23_162000). On ne les supprime pas ici.
    }
};
