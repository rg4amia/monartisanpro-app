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
        Schema::create('labor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained()->onDelete('cascade');
            $table->foreignId('artisan_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('escrow_wallet_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['scheduled', 'processing', 'completed', 'failed'])->default('scheduled');
            $table->timestamp('scheduled_at'); // J+1 after validation
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['artisan_id', 'status']);
            $table->index(['milestone_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labor_payments');
    }
};
