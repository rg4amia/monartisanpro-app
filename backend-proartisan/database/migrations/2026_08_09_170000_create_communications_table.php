<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['annonce', 'le_saviez_vous'])->default('annonce');
            $table->string('titre', 255);
            $table->text('contenu');
            $table->json('cibles_json'); // ["client","artisan","fournisseur","livreur"]
            $table->enum('statut', ['brouillon', 'publie', 'cloture'])->default('brouillon');
            $table->foreignId('auteur_id')->constrained('users');
            $table->timestamp('publie_at')->nullable();
            $table->timestamp('cloture_at')->nullable();
            $table->timestamps();

            $table->index(['statut', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
