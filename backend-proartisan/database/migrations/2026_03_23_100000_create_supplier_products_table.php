<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 60)->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('unit_price');
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
            $table->unique(['supplier_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
