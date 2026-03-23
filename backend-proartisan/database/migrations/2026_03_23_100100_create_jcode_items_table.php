<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jcode_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jcode_id')->constrained('jcodes')->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained('supplier_products')->nullOnDelete();
            $table->enum('source', ['catalog', 'custom'])->default('catalog');
            $table->string('item_name');
            $table->string('item_sku', 60)->nullable();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price')->nullable();
            $table->bigInteger('subtotal')->default(0);
            $table->enum('status', ['requested', 'served'])->default('requested');
            $table->timestamps();

            $table->index(['jcode_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jcode_items');
    }
};
