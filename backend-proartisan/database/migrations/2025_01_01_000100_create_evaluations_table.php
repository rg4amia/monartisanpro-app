<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluateur_id')->constrained('users');
            $table->foreignId('evalue_id')->constrained('users');
            $table->unsignedTinyInteger('note'); // 1-5
            $table->text('commentaire')->nullable();
            // Critères Score N'Zassa (1-5 chacun)
            $table->unsignedTinyInteger('fiabilite')->nullable();
            $table->unsignedTinyInteger('integrite')->nullable();
            $table->unsignedTinyInteger('qualite')->nullable();
            $table->unsignedTinyInteger('reactivite')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
