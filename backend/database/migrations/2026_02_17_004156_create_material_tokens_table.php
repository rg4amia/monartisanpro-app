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
        Schema::create('material_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g., PA-4592
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('escrow_wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('total_value', 12, 2);
            $table->decimal('remaining_value', 12, 2);
            $table->string('qr_code_path')->nullable();
            $table->timestamp('expires_at');
            $table->enum('status', ['active', 'partially_used', 'fully_used', 'expired'])->default('active');
            $table->timestamps();

            $table->index('code');
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_tokens');
    }
};
