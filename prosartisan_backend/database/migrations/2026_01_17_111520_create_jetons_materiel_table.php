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
        
        Schema::create('jetons_materiel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sequestre_id');
            $table->uuid('artisan_id');
            $table->string('code', 10)->unique(); // PA-XXXX format
            $table->bigInteger('total_amount_centimes');
            $table->bigInteger('used_amount_centimes')->default(0);
            $table->json('authorized_suppliers')->nullable(); // Array of supplier UUIDs
            $table->string('status', 50)->default('ACTIVE');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('sequestre_id')->references('id')->on('sequestres')->onDelete('cascade');
            $table->foreign('artisan_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('sequestre_id');
            $table->index('artisan_id');
            $table->index('code');
            $table->index('status');
            $table->index('expires_at');
            $table->index('created_at');

            // Composite index for finding active jetons by artisan
            $table->index(['artisan_id', 'status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jetons_materiel');
    }
};
