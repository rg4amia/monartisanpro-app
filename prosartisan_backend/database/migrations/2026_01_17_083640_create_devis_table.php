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
        Schema::create('devis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mission_id');
            $table->uuid('artisan_id');
            $table->bigInteger('total_amount_centimes');
            $table->bigInteger('materials_amount_centimes');
            $table->bigInteger('labor_amount_centimes');
            $table->string('status', 50)->default('PENDING'); // PENDING, ACCEPTED, REJECTED
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('mission_id')->references('id')->on('missions')->onDelete('cascade');
            $table->foreign('artisan_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('mission_id');
            $table->index('artisan_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devis');
    }
};
