<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Chantier 9A : Grand Livre en Partie Double (Double-Entry Ledger Pur)
     */
    public function up(): void
    {
        if (! Schema::hasTable('double_entry_ledger_entries')) {
            Schema::create('double_entry_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('transaction_group_id')->index()->comment('UUID liant les débits et crédits de la même opération');
                $table->string('account_source', 64)->index()->comment('Compte débité (ex: client_escrow, platform_escrow_mo)');
                $table->string('account_destination', 64)->index()->comment('Compte crédité (ex: artisan_cashable, supplier_payable)');
                $table->unsignedBigInteger('amount')->comment('Montant en FCFA (entier strict, jamais de flottant)');
                $table->string('currency', 3)->default('XOF')->comment('Devise officielle (XOF / FCFA)');
                $table->string('entry_type', 50)->index()->comment('Type d opération (escrow_deposit, milestone_release, etc.)');
                
                $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
                $table->foreignId('jalon_id')->nullable()->constrained('jalons')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->text('description')->nullable();
                $table->json('metadata_json')->nullable();

                // Table immuable append-only : uniquement created_at, jamais de modifications
                $table->timestamp('created_at')->useCurrent()->index();

                // Index composés pour vérification comptable rapide
                $table->index(['account_source', 'created_at']);
                $table->index(['account_destination', 'created_at']);
                $table->index(['mission_id', 'entry_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('double_entry_ledger_entries');
    }
};
