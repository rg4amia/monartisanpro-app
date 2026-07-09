<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parrainages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parrain_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('filleul_id')->constrained('users')->onDelete('cascade');
            $table->bigInteger('score_caution')->default(0);
            $table->timestamps();

            $table->unique('filleul_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parrainages');
    }
};
