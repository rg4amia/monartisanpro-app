<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('litiges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('declencheur_id')->constrained('users');
            $table->enum('type', ['client', 'artisan']);
            $table->text('description')->nullable();
            $table->enum('statut', ['ouvert', 'en_cours', 'resolu'])->default('ouvert');
            $table->enum('decision', ['client', 'artisan', 'gel'])->nullable();
            $table->timestamp('resolu_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litiges');
    }
};
