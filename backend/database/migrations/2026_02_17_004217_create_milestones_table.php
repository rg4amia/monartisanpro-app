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
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('labor_percentage', 5, 2); // Portion of labor wallet (0-100%)
            $table->integer('sequence_order')->default(1);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'validated', 'paid'])->default('pending');
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_otp')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('sequence_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
