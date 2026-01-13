<?php

// ============================================================================
// database/migrations/2024_01_01_000001_create_clients_table.php
// ============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('email')->nullable()->unique();
            $table->string('telephone')->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('adresse')->nullable();
            $table->string('commune')->nullable();
            $table->string('ville')->default('Abidjan');
            $table->enum('statut', ['actif', 'suspendu', 'inactif'])->default('actif');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('telephone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000002_create_categories_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->string('icone')->nullable();
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Seeder pour les 3 catégories pilotes
        DB::table('categories')->insert([
            ['nom' => 'Plomberie', 'slug' => 'plomberie', 'actif' => true],
            ['nom' => 'Électricité', 'slug' => 'electricite', 'actif' => true],
            ['nom' => 'Maçonnerie', 'slug' => 'maconnerie', 'actif' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000003_create_artisans_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artisans', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('email')->nullable()->unique();
            $table->string('telephone')->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            
            // Métier
            $table->foreignId('categorie_id')->constrained('categories');
            $table->json('specialites')->nullable(); // ["Installation sanitaire", "Réparation fuite"]
            $table->integer('annees_experience')->default(0);
            $table->json('certifications')->nullable();
            
            // Géolocalisation
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->float('rayon_intervention_km')->default(10);
            $table->string('adresse')->nullable();
            $table->string('commune')->nullable();
            $table->string('ville')->default('Abidjan');
            
            // KYC
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('kyc_documents')->nullable(); // {cni: url, selfie: url}
            $table->text('kyc_rejection_reason')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            
            $table->enum('statut', ['actif', 'suspendu', 'inactif'])->default('actif');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('telephone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour recherche géographique
            $table->index(['latitude', 'longitude']);
            $table->index(['categorie_id', 'kyc_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artisans');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000004_create_fournisseurs_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom_etablissement');
            $table->string('nom_responsable');
            $table->string('prenoms_responsable');
            $table->string('email')->nullable()->unique();
            $table->string('telephone')->unique();
            $table->string('password');
            $table->string('logo')->nullable();
            
            // Localisation
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('adresse');
            $table->string('commune');
            $table->string('ville')->default('Abidjan');
            
            // Agrément
            $table->boolean('agree')->default(false);
            $table->string('numero_agrement')->nullable();
            $table->timestamp('date_agrement')->nullable();
            
            $table->enum('statut', ['actif', 'suspendu', 'inactif'])->default('actif');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000005_create_referents_zone_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referents_zone', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('telephone')->unique();
            $table->string('email')->nullable()->unique();
            $table->json('zones_intervention'); // ["Cocody", "Marcory"]
            $table->decimal('taux_commission', 5, 2)->default(2.00); // 2%
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referents_zone');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000010_create_missions_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // MISS-2024-0001
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('artisan_id')->nullable()->constrained('artisans');
            $table->foreignId('categorie_id')->constrained('categories');
            
            $table->string('titre');
            $table->text('description');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('adresse');
            $table->string('commune');
            
            $table->date('date_souhaite')->nullable();
            $table->enum('urgence', ['normale', 'urgent', 'tres_urgent'])->default('normale');
            
            $table->enum('statut', [
                'brouillon',
                'publiee',
                'devis_recus',
                'devis_accepte',
                'en_cours',
                'terminee',
                'annulee'
            ])->default('brouillon');
            
            $table->timestamp('publiee_at')->nullable();
            $table->timestamp('acceptee_at')->nullable();
            $table->timestamp('demarree_at')->nullable();
            $table->timestamp('terminee_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['statut', 'categorie_id']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000011_create_devis_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // DEV-2024-0001
            $table->foreignId('mission_id')->constrained('missions')->onDelete('cascade');
            $table->foreignId('artisan_id')->constrained('artisans');
            
            $table->decimal('montant_materiel', 12, 2);
            $table->decimal('montant_main_oeuvre', 12, 2);
            $table->decimal('montant_total', 12, 2);
            
            $table->text('description_travaux');
            $table->integer('duree_estimee_jours');
            $table->date('date_debut_prevue');
            
            $table->enum('statut', ['envoye', 'accepte', 'refuse', 'expire'])->default('envoye');
            $table->text('raison_refus')->nullable();
            $table->timestamp('accepte_at')->nullable();
            $table->timestamp('refuse_at')->nullable();
            $table->timestamp('expire_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['mission_id', 'statut']);
        });

        Schema::create('devis_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained('devis')->onDelete('cascade');
            $table->string('designation');
            $table->integer('quantite');
            $table->string('unite'); // pièce, m², ml, etc.
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('prix_total', 10, 2);
            $table->enum('type', ['materiel', 'main_oeuvre']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis_lignes');
        Schema::dropIfExists('devis');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000020_create_sequestres_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions');
            $table->foreignId('devis_id')->constrained('devis');
            
            $table->decimal('montant_total', 12, 2);
            $table->decimal('montant_materiel', 12, 2);
            $table->decimal('montant_main_oeuvre', 12, 2);
            $table->decimal('montant_materiel_utilise', 12, 2)->default(0);
            $table->decimal('montant_main_oeuvre_libere', 12, 2)->default(0);
            
            $table->enum('statut', ['bloque', 'partiel', 'libere', 'rembourse'])->default('bloque');
            
            $table->string('transaction_mobile_money_id')->nullable();
            $table->string('operateur')->nullable(); // wave, orange, mtn
            
            $table->timestamp('date_blocage');
            $table->timestamp('date_liberation')->nullable();
            
            $table->timestamps();
            
            $table->index(['mission_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequestres');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000021_create_jetons_materiel_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jetons_materiel', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // PA-XXXX
            $table->foreignId('sequestre_id')->constrained('sequestres');
            $table->foreignId('artisan_id')->constrained('artisans');
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs');
            
            $table->decimal('montant_initial', 10, 2);
            $table->decimal('montant_utilise', 10, 2)->default(0);
            $table->decimal('montant_restant', 10, 2);
            
            $table->enum('statut', ['actif', 'utilise', 'expire', 'annule'])->default('actif');
            
            $table->timestamp('date_expiration');
            
            // Validation GPS anti-fraude
            $table->decimal('latitude_validation', 10, 8)->nullable();
            $table->decimal('longitude_validation', 11, 8)->nullable();
            $table->timestamp('date_validation')->nullable();
            
            $table->timestamps();
            
            $table->index(['code', 'statut']);
            $table->index(['artisan_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jetons_materiel');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000030_create_chantiers_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chantiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions');
            $table->foreignId('artisan_id')->constrained('artisans');
            $table->foreignId('client_id')->constrained('clients');
            
            $table->date('date_debut_reelle')->nullable();
            $table->date('date_fin_reelle')->nullable();
            $table->date('date_fin_prevue');
            
            $table->enum('statut', ['en_attente', 'en_cours', 'termine', 'abandonne'])->default('en_attente');
            
            $table->integer('progression_pourcentage')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chantiers');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000031_create_jalons_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jalons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chantier_id')->constrained('chantiers')->onDelete('cascade');
            
            $table->string('titre');
            $table->text('description')->nullable();
            $table->integer('ordre');
            $table->integer('pourcentage_progression')->default(0);
            
            $table->decimal('montant_a_liberer', 10, 2);
            
            $table->enum('statut', ['en_attente', 'en_cours', 'valide', 'refuse'])->default('en_attente');
            
            $table->timestamp('date_soumission')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->string('code_otp_validation', 6)->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jalons');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000032_create_preuves_livraison_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preuves_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jalon_id')->constrained('jalons')->onDelete('cascade');
            $table->foreignId('artisan_id')->constrained('artisans');
            
            $table->string('photo_url');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('description')->nullable();
            
            $table->timestamp('date_upload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preuves_livraison');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000040_create_scores_nzassa_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores_nzassa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->unique()->constrained('artisans');
            
            $table->integer('score_total')->default(0); // 0-100
            $table->float('score_fiabilite')->default(0); // 0-40
            $table->float('score_integrite')->default(30); // 0-30 (démarre à 30)
            $table->float('score_qualite')->default(0); // 0-20
            $table->float('score_reactivite')->default(0); // 0-10
            
            // Métriques
            $table->integer('nombre_chantiers')->default(0);
            $table->integer('nombre_chantiers_termines')->default(0);
            $table->decimal('moyenne_notes_client', 3, 2)->default(0);
            $table->float('temps_reponse_moyen_heures')->default(0);
            $table->integer('tentatives_contournement')->default(0);
            
            // Historique pour audit bancaire
            $table->json('historique')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores_nzassa');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000041_create_evaluations_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions');
            $table->foreignId('artisan_id')->constrained('artisans');
            $table->foreignId('client_id')->constrained('clients');
            
            $table->integer('note')->unsigned(); // 1-5
            $table->text('commentaire')->nullable();
            
            $table->integer('note_qualite')->nullable();
            $table->integer('note_ponctualite')->nullable();
            $table->integer('note_communication')->nullable();
            $table->integer('note_proprete')->nullable();
            
            $table->timestamps();
            
            $table->index(['artisan_id', 'note']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};

// ============================================================================
// database/migrations/2024_01_01_000050_create_litiges_table.php
// ============================================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('litiges', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // LIT-2024-0001
            $table->foreignId('mission_id')->constrained('missions');
            $table->foreignId('declarant_id'); // peut être client ou artisan
            $table->string('declarant_type'); // Client ou Artisan
            
            $table->enum('type', [
                'qualite_travaux',
                'retard',
                'materiel',
                'paiement',
                'abandon',
                'autre'
            ]);
            
            $table->text('description');
            $table->json('preuves')->nullable(); // URLs des photos
            
            $table->enum('statut', [
                'ouvert',
                'en_mediation',
                'en_arbitrage',
                'resolu',
                'clos'
            ])->default('ouvert');
            
            $table->enum('decision', [
                'en_attente',
                'client_raison',
                'artisan_raison',
                'partage',
                'sans_suite'
            ])->nullable();
            
            $table->foreignId('referent_id')->nullable()->constrained('referents_zone');
            $table->text('decision_justification')->nullable();
            $table->decimal('montant_rembourse', 10, 2)->nullable();
            
            $table->timestamp('date_resolution')->nullable();
            $table->timestamps();
            
            $table->index(['statut', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('litiges');
    }
};