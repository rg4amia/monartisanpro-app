<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 15 — annuaire artisans.
 *
 * - `artisan_availabilities` : une ligne par version de disponibilité
 *   (statut, jours et horaires, nuit), soumise à validation avant
 *   publication ; la version publiée est la dernière validée.
 * - `users.directory_hidden_*` : retrait d'un artisan de l'annuaire du site
 *   vitrine par un administrateur (actif par défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('artisan_availabilities')) {
            Schema::create('artisan_availabilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 20); // disponible | occupe | conge
                $table->date('until_date')->nullable();
                $table->json('schedule_json');
                $table->boolean('night_work')->default(false);
                $table->string('review_status', 20)->default('en_attente'); // en_attente | validee | refusee | remplacee
                $table->string('rejection_reason', 500)->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'review_status']);
                $table->index(['review_status', 'created_at']);
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'directory_hidden_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('directory_hidden_at')->nullable();
                $table->unsignedBigInteger('directory_hidden_by')->nullable();
                $table->string('directory_hidden_reason', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'directory_hidden_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['directory_hidden_at', 'directory_hidden_by', 'directory_hidden_reason']);
            });
        }

        Schema::dropIfExists('artisan_availabilities');
    }
};
