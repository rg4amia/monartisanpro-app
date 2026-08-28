<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Slides Hero
        Schema::create('vitrine_slides', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 255);
            $table->text('sous_titre')->nullable();
            $table->text('image_url');
            $table->string('cta_texte', 100)->nullable();
            $table->string('cta_lien', 255)->nullable();
            $table->tinyInteger('ordre')->unsigned()->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // 2. Artisan du Mois
        Schema::create('vitrine_artisan_du_mois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('mois')->unique(); // Premier jour du mois
            $table->text('photo_override_url')->nullable();
            $table->text('texte_editorial')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // 3. Articles / Actualités
        Schema::create('vitrine_articles', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 255);
            $table->string('slug', 191)->unique();
            $table->longText('contenu');
            $table->text('image_url')->nullable();
            $table->enum('categorie', ['actualite', 'evenement', 'temoignage', 'partenariat'])->default('actualite');
            $table->boolean('publie')->default(false);
            $table->timestamp('publie_at')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        // 4. Capsules Vidéo
        Schema::create('vitrine_videos', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 255);
            $table->text('description')->nullable();
            $table->text('video_url');
            $table->text('thumbnail_url')->nullable();
            $table->enum('categorie', ['capsule', 'formation', 'temoignage', 'evenement'])->default('capsule');
            $table->tinyInteger('ordre')->unsigned()->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // 5. Sessions de Formation
        Schema::create('vitrine_formations', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 255);
            $table->text('description');
            $table->text('image_url')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('lieu', 255);
            $table->string('formateur', 255)->nullable();
            $table->unsignedInteger('places_total')->nullable();
            $table->unsignedInteger('places_restantes')->nullable();
            $table->bigInteger('tarif')->default(0); // FCFA
            $table->string('lien_inscription', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // 6. Offres de Recrutement
        Schema::create('vitrine_recrutements', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 255);
            $table->text('description');
            $table->string('metier', 150);
            $table->string('lieu', 255);
            $table->enum('type_contrat', ['cdi', 'cdd', 'stage', 'freelance', 'apprentissage']);
            $table->date('date_limite')->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // 7. Pop-ups Promotionnels
        Schema::create('vitrine_popups', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 255);
            $table->text('contenu')->nullable();
            $table->text('image_url')->nullable();
            $table->string('lien_cta', 255)->nullable();
            $table->string('texte_cta', 100)->nullable();
            $table->timestamp('date_debut');
            $table->timestamp('date_fin');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // 8. Paramètres Vitrine (clé-valeur)
        Schema::create('vitrine_settings', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->text('valeur');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vitrine_settings');
        Schema::dropIfExists('vitrine_popups');
        Schema::dropIfExists('vitrine_recrutements');
        Schema::dropIfExists('vitrine_formations');
        Schema::dropIfExists('vitrine_videos');
        Schema::dropIfExists('vitrine_articles');
        Schema::dropIfExists('vitrine_artisan_du_mois');
        Schema::dropIfExists('vitrine_slides');
    }
};
