<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liens de téléchargement de l'application mobile (Google Play, App Store)
 * gérés depuis le backoffice (Chantier 16) : brouillon, publié, désactivé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_store_links')) {
            return;
        }

        Schema::create('app_store_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('url', 500);
            $table->string('status', 20)->default('brouillon');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_store_links');
    }
};
