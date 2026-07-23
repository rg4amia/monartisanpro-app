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
        Schema::create('artisan_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->constrained('users')->cascadeOnDelete();
            $table->text('description');
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_cost')->default(0); // FCFA
            $table->enum('condition', ['neuf', 'occasion'])->default('neuf');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artisan_stocks');
    }
};
