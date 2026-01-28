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
        Schema::create('jeton_validations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jeton_id');
            $table->uuid('fournisseur_id');
            $table->uuid('artisan_id');
            $table->bigInteger('amount_used_centimes');
            $table->decimal('artisan_latitude', 10, 8);
            $table->decimal('artisan_longitude', 11, 8);
            $table->decimal('supplier_latitude', 10, 8);
            $table->decimal('supplier_longitude', 11, 8);
            $table->decimal('distance_meters', 8, 2);
            $table->string('validation_status', 50)->default('SUCCESS');
            $table->text('validation_notes')->nullable();
            $table->timestamp('validated_at');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('jeton_id')->references('id')->on('jetons_materiel')->onDelete('cascade');
            $table->foreign('fournisseur_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('artisan_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('jeton_id');
            $table->index('fournisseur_id');
            $table->index('artisan_id');
            $table->index('validation_status');
            $table->index('validated_at');
            $table->index('created_at');

            // Composite indexes for common queries
            $table->index(['jeton_id', 'validated_at']);
            $table->index(['fournisseur_id', 'validated_at']);
            $table->index(['artisan_id', 'validated_at']);

            // Note: This table is append-only for audit purposes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jeton_validations');
    }
};
