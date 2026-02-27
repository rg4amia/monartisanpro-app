<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['acompte', 'liberation_jalon', 'paiement_fournisseur', 'remboursement', 'credit']);
            $table->bigInteger('montant');
            $table->string('wallet_source', 50);
            $table->string('wallet_dest', 50);
            $table->enum('provider', ['wave', 'orange_money', 'virement_bancaire']);
            $table->enum('statut', ['en_attente', 'confirme', 'echoue'])->default('en_attente');
            $table->string('reference_externe', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
