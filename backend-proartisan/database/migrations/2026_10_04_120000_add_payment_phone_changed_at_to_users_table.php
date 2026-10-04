<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 27, lot E — date du dernier changement du numéro de paiement.
 *
 * Les retraits et les versements d'un compte sont suspendus pendant un délai
 * après ce changement : le titulaire, prévenu par SMS, a le temps de réagir
 * si le changement n'est pas le sien. Idempotente (Règle d'or 55).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'payment_phone_changed_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('payment_phone_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'payment_phone_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('payment_phone_changed_at');
            });
        }
    }
};
