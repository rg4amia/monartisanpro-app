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
        Schema::create('devis_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('devis_id');
            $table->text('description');
            $table->integer('quantity');
            $table->bigInteger('unit_price_centimes');
            $table->string('line_type', 50); // MATERIAL or LABOR
            $table->timestamp('created_at');

            // Foreign key constraint
            $table->foreign('devis_id')->references('id')->on('devis')->onDelete('cascade');

            // Indexes for performance
            $table->index('devis_id');
            $table->index('line_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devis_lines');
    }
};
