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
        Schema::create('delivery_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->float('speed_kmh')->nullable();
            $table->float('heading')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['order_id', 'created_at']);
            $table->index(['driver_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_trackings');
    }
};
