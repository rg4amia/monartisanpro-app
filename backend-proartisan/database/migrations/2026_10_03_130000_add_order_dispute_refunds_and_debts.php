<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 22 — effet financier d'un litige de commande : gel à l'ouverture,
 * remboursement à la clôture, dette du responsable et son recouvrement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_disputes')) {
            Schema::table('order_disputes', function (Blueprint $table) {
                if (! Schema::hasColumn('order_disputes', 'frozen_amount')) {
                    $table->bigInteger('frozen_amount')->default(0);
                }
                if (! Schema::hasColumn('order_disputes', 'frozen_user_id')) {
                    $table->unsignedBigInteger('frozen_user_id')->nullable();
                }
                if (! Schema::hasColumn('order_disputes', 'refund_amount')) {
                    $table->bigInteger('refund_amount')->default(0);
                }
                if (! Schema::hasColumn('order_disputes', 'fare_refund')) {
                    $table->bigInteger('fare_refund')->default(0);
                }
                if (! Schema::hasColumn('order_disputes', 'responsible_role')) {
                    $table->string('responsible_role', 20)->nullable();
                }
                if (! Schema::hasColumn('order_disputes', 'responsible_user_id')) {
                    $table->unsignedBigInteger('responsible_user_id')->nullable();
                }
                if (! Schema::hasColumn('order_disputes', 'refund_payout_id')) {
                    $table->unsignedBigInteger('refund_payout_id')->nullable();
                }
            });
        }

        if (! Schema::hasTable('order_dispute_debts')) {
            Schema::create('order_dispute_debts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_dispute_id')->constrained('order_disputes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('wallet_type', 30);
                $table->bigInteger('montant');
                $table->bigInteger('montant_recouvre')->default(0);
                $table->string('statut', 20)->default('en_cours');
                $table->dateTime('settled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'statut']);
            });
        }

        // Historique append-only : une ligne par recouvrement, jamais modifiée.
        if (! Schema::hasTable('order_dispute_debt_entries')) {
            Schema::create('order_dispute_debt_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_dispute_debt_id')->constrained('order_dispute_debts')->cascadeOnDelete();
                $table->bigInteger('montant');
                $table->string('source', 30);
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->dateTime('created_at');

                $table->unique(['order_dispute_debt_id', 'transaction_id'], 'dispute_debt_entry_tx_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_dispute_debt_entries');
        Schema::dropIfExists('order_dispute_debts');
    }
};
