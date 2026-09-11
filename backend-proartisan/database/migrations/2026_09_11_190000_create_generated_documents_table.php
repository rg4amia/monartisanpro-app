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
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('document_type', 50)->index(); // recu_liberation_jalon, recu_paiement_fournisseur, recu_cashout, recu_remboursement, facture_litige, rapport_solvabilite
            $table->string('title', 255);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('supplier_cashout_id')->nullable()->constrained('supplier_cashouts')->nullOnDelete();
            $table->foreignId('litige_id')->nullable()->constrained('litiges')->nullOnDelete();
            $table->unsignedBigInteger('montant')->default(0); // Montant FCFA entier
            $table->string('file_path')->nullable();
            $table->string('mime_type', 50)->default('application/pdf');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['mission_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
