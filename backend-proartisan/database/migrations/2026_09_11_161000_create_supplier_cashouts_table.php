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
        Schema::create('supplier_cashouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique(); // Ex: CSH-20260911-A1B2
            $table->foreignId('supplier_id')->constrained('users')->cascadeOnDelete();
            $table->string('beneficiary_name', 150);
            $table->string('beneficiary_phone', 20);
            $table->unsignedBigInteger('montant_brut'); // FCFA
            $table->decimal('commission_rate', 5, 4)->default(0.0250); // Ex: 0.0250 = 2.5%
            $table->unsignedBigInteger('montant_commission')->default(0); // FCFA commission perçue par la quincaillerie
            $table->unsignedBigInteger('montant_net'); // FCFA
            $table->enum('statut', ['en_attente', 'approuve', 'complete', 'rejete'])->default('en_attente');
            $table->enum('mode_retrait', ['especes_guichet', 'wave', 'orange_money'])->default('especes_guichet');
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'statut']);
            $table->index('statut');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_cashouts');
    }
};
