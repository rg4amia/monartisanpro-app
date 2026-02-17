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
        Schema::create('escrow_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('material_wallet', 12, 2);
            $table->decimal('labor_wallet', 12, 2);
            $table->decimal('material_spent', 12, 2)->default(0);
            $table->decimal('labor_released', 12, 2)->default(0);
            $table->enum('status', ['active', 'completed', 'refunded'])->default('active');
            $table->timestamps();

            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_wallets');
    }
};
