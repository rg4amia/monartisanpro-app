<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versements Mobile Money sortants (artisan, livreur) et leur historique.
 *
 * Un versement échoué ne débite plus le portefeuille : il reste relançable
 * et chaque action (tentative, échec, relance, versement manuel, annulation)
 * est consignée dans `mobile_money_payout_events`, en ajout seul.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_money_payouts')) {
            Schema::create('mobile_money_payouts', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('wallet_type', 30);
                $table->string('context', 40);
                $table->unsignedBigInteger('montant');
                $table->unsignedBigInteger('montant_transfere')->nullable();
                $table->boolean('phone_locked')->default(false);
                $table->string('provider', 30);
                $table->string('phone', 20)->nullable();
                $table->string('statut', 20)->default('en_cours');
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->dateTime('next_retry_at')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->string('external_reference', 120)->nullable();
                $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
                $table->foreignId('jalon_id')->nullable()->constrained('jalons')->nullOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->string('description', 255)->nullable();
                $table->json('ledger_metadata')->nullable();
                $table->timestamps();

                $table->index(['statut', 'next_retry_at']);
                $table->index(['user_id', 'statut']);
                $table->index(['mission_id', 'statut']);
            });
        }

        if (! Schema::hasTable('mobile_money_payout_events')) {
            Schema::create('mobile_money_payout_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payout_id')->constrained('mobile_money_payouts')->cascadeOnDelete();
                $table->string('action', 40);
                $table->string('statut_avant', 20)->nullable();
                $table->string('statut_apres', 20)->nullable();
                $table->text('message')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->dateTime('created_at');

                $table->index(['payout_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_payout_events');
        Schema::dropIfExists('mobile_money_payouts');
    }
};
