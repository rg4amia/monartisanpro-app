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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('artisan_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('trade_id')->constrained('trades')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->geometry('location', 'point');
            $table->string('address')->nullable();
            $table->enum('status', [
                'pending',
                'awaiting_quotes',
                'quote_received',
                'quote_accepted',
                'payment_pending',
                'in_progress',
                'completed',
                'cancelled',
                'disputed'
            ])->default('pending');
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->decimal('final_amount', 12, 2)->nullable();
            $table->integer('quote_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->spatialIndex('location');
            $table->index(['client_id', 'status']);
            $table->index(['artisan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
