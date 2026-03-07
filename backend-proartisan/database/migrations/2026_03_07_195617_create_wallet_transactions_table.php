<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('wallet_type', ['wallet_materiaux', 'wallet_mo'])->comment('Type de wallet');
            $table->enum('operation', ['credit', 'debit', 'blocage', 'deblocage'])->comment('Type d\'opération');
            $table->bigInteger('montant')->comment('Montant en FCFA (toujours positif)');
            $table->bigInteger('solde_avant')->comment('Solde avant opération en FCFA');
            $table->bigInteger('solde_apres')->comment('Solde après opération en FCFA');
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('jalon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete()->comment('Lien vers transactions (paiements externes)');
            $table->string('reference', 100)->unique()->comment('Référence unique de la transaction wallet');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable()->comment('Données complémentaires (ex: details jalon)');
            $table->timestamps();

            // Index pour performances
            $table->index(['user_id', 'wallet_type', 'created_at']);
            $table->index('mission_id');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
