<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 500);
            $table->text('reponse');
            $table->string('categorie', 100)->nullable();
            // Rôles mobile concernés : ["client","artisan","livreur","fournisseur"].
            $table->json('roles');
            $table->tinyInteger('ordre')->unsigned()->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
