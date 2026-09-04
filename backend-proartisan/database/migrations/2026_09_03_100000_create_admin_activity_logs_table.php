<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier C3 (P0-4) — journal d'audit des actions administrateur.
 *
 * Chaque action sensible du backoffice (revue KYC, arbitrage de litige, gel de
 * score, suppression / suspension de compte, modification de paramètres, échecs
 * de connexion admin…) écrit une ligne horodatée et attribuée ici.
 *
 * La table est en append-only : aucune colonne `updated_at`, aucune mise à jour
 * ni suppression applicative.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            // Dénormalisé : reste lisible même si le compte admin est supprimé ensuite.
            $table->string('admin_name', 150)->nullable();
            $table->string('action', 100);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 200)->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('action');
            $table->index('admin_id');
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
