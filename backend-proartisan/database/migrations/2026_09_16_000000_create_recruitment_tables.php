<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module de recrutement BTP & Métiers — mise en relation temps réel entre
 * clients/fournisseurs recruteurs et artisans, distincte des annonces
 * carrière du site vitrine (`vitrine_recrutements`).
 *
 * MySQL 5.7 : `position` est un POINT sans SRID (ajouté via DB::statement,
 * cf. CLAUDE.md règle MySQL) — pas d'index spatial car la colonne est
 * nullable (une offre peut n'avoir qu'une commune, sans GPS précis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users');
            $table->enum('creator_type', ['admin', 'client', 'fournisseur']);
            $table->foreignId('trade_id')->constrained('trades');
            $table->string('title', 150);
            $table->text('description');
            $table->enum('mission_type', ['tacheron_brigade', 'journalier', 'longue_duree', 'urgence']);
            $table->string('commune', 100);
            $table->string('sous_quartier', 150)->nullable();
            $table->bigInteger('daily_rate_min')->nullable();
            $table->bigInteger('daily_rate_max')->nullable();
            $table->smallInteger('openings_count')->unsigned()->default(1);
            $table->dateTime('deadline_at')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'active', 'filled', 'expired', 'cancelled'])
                ->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('deadline_at');
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE recruitment_offers ADD COLUMN position POINT NULL AFTER sous_quartier');
        } else {
            Schema::table('recruitment_offers', function (Blueprint $table) {
                $table->string('position')->nullable(); // Simple string pour les tests SQLite
            });
        }

        Schema::create('recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('recruitment_offers')->cascadeOnDelete();
            $table->foreignId('artisan_id')->constrained('users');
            $table->decimal('matching_score', 5, 2)->nullable();
            $table->enum('status', ['submitted', 'shortlisted', 'contacted', 'rejected', 'confirmed'])
                ->default('submitted');
            $table->dateTime('applied_at');
            $table->timestamps();

            $table->unique(['offer_id', 'artisan_id']);
        });

        Schema::create('recruitment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('recruitment_settings')->insert([
            [
                'key' => 'client_posting_enabled',
                'value' => '1',
                'description' => 'Autoriser les clients à publier des offres de recrutement depuis leur espace mobile.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'fournisseur_posting_enabled',
                'value' => '1',
                'description' => 'Autoriser les fournisseurs à publier des offres de recrutement depuis leur espace mobile.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_settings');
        Schema::dropIfExists('recruitment_applications');
        Schema::dropIfExists('recruitment_offers');
    }
};
