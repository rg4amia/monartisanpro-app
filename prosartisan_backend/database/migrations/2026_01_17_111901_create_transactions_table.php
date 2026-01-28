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
        
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id')->nullable();
            $table->bigInteger('amount_centimes');
            $table->string('type', 50);
            $table->string('status', 50)->default('PENDING');
            $table->string('mobile_money_reference')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Foreign key constraints (nullable for system transactions)
            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('to_user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('from_user_id');
            $table->index('to_user_id');
            $table->index('type');
            $table->index('status');
            $table->index('mobile_money_reference');
            $table->index('created_at');
            $table->index('completed_at');

            // Composite indexes for common queries
            $table->index(['from_user_id', 'created_at']);
            $table->index(['to_user_id', 'created_at']);
            $table->index(['status', 'created_at']);

            // Note: No updated_at column - transactions are immutable audit records
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
