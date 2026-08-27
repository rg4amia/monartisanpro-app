<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consommation Partielle du J-Code — PRD §5 Point 2.
 *
 * Permet à un J-Code d'être débité partiellement chez plusieurs fournisseurs agréés.
 * Ajout de :
 * - jcodes.montant_consomme (suivi du solde consommé)
 * - jcodes.statut → nouvelle valeur 'partiellement_utilise'
 * - jcode_items.served_by_supplier_id (traçabilité du fournisseur par item)
 * - jcode_items.quantity_served (quantité effectivement servie, pour servir partiellement un item)
 * - jcode_items.status → nouvelle valeur 'partial'
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            // Drop and recreate tables to update ENUM check constraints in SQLite
            Schema::dropIfExists('jcode_items');
            Schema::dropIfExists('jcodes');

            Schema::create('jcodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained();
                $table->foreignId('artisan_id')->constrained('users');
                $table->foreignId('fournisseur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('code', 7)->unique();
                $table->text('qr_url')->nullable();
                $table->string('ussd_code', 20)->nullable();
                $table->string('photo_materiaux_url', 500)->nullable();
                $table->decimal('photo_latitude', 10, 8)->nullable();
                $table->decimal('photo_longitude', 11, 8)->nullable();
                $table->timestamp('photo_taken_at')->nullable();
                $table->bigInteger('montant');
                $table->bigInteger('montant_consomme')->default(0);
                $table->enum('statut', ['actif', 'partiellement_utilise', 'utilise', 'expire'])->default('actif');
                $table->string('position_scan')->nullable();
                $table->enum('paiement_status', ['en_attente', 'programme', 'paye'])->default('en_attente');
                $table->timestamp('scanned_at')->nullable();
                $table->timestamp('paye_at')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();
            });

            Schema::create('jcode_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jcode_id')->constrained('jcodes')->cascadeOnDelete();
                $table->foreignId('supplier_product_id')->nullable()->constrained('supplier_products')->nullOnDelete();
                $table->enum('source', ['catalog', 'custom'])->default('catalog');
                $table->string('item_name');
                $table->string('item_sku', 60)->nullable();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('quantity_served')->default(0);
                $table->bigInteger('unit_price')->nullable();
                $table->bigInteger('subtotal')->default(0);
                $table->enum('status', ['requested', 'partial', 'served'])->default('requested');
                $table->foreignId('served_by_supplier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['jcode_id', 'status']);
            });
        } else {
            // 1. Ajouter montant_consomme sur jcodes
            Schema::table('jcodes', function (Blueprint $table) {
                $table->bigInteger('montant_consomme')->default(0)->after('montant');
            });

            // 2. Modifier l'ENUM statut pour ajouter 'partiellement_utilise'
            DB::statement("ALTER TABLE jcodes MODIFY COLUMN statut ENUM('actif','partiellement_utilise','utilise','expire') NOT NULL DEFAULT 'actif'");

            // 3. Ajouter served_by_supplier_id et quantity_served sur jcode_items
            Schema::table('jcode_items', function (Blueprint $table) {
                $table->foreignId('served_by_supplier_id')->nullable()->after('status')
                      ->constrained('users')->nullOnDelete();
                $table->unsignedInteger('quantity_served')->default(0)->after('quantity');
            });

            // 4. Modifier l'ENUM status des items pour ajouter 'partial'
            DB::statement("ALTER TABLE jcode_items MODIFY COLUMN status ENUM('requested','partial','served') NOT NULL DEFAULT 'requested'");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            Schema::dropIfExists('jcode_items');
            Schema::dropIfExists('jcodes');

            Schema::create('jcodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained();
                $table->foreignId('artisan_id')->constrained('users');
                $table->foreignId('fournisseur_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('code', 7)->unique();
                $table->text('qr_url')->nullable();
                $table->string('ussd_code', 20)->nullable();
                $table->string('photo_materiaux_url', 500)->nullable();
                $table->decimal('photo_latitude', 10, 8)->nullable();
                $table->decimal('photo_longitude', 11, 8)->nullable();
                $table->timestamp('photo_taken_at')->nullable();
                $table->bigInteger('montant');
                $table->enum('statut', ['actif', 'utilise', 'expire'])->default('actif');
                $table->string('position_scan')->nullable();
                $table->enum('paiement_status', ['en_attente', 'programme', 'paye'])->default('en_attente');
                $table->timestamp('scanned_at')->nullable();
                $table->timestamp('paye_at')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();
            });

            Schema::create('jcode_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jcode_id')->constrained('jcodes')->cascadeOnDelete();
                $table->foreignId('supplier_product_id')->nullable()->constrained('supplier_products')->nullOnDelete();
                $table->enum('source', ['catalog', 'custom'])->default('catalog');
                $table->string('item_name');
                $table->string('item_sku', 60)->nullable();
                $table->unsignedInteger('quantity');
                $table->bigInteger('unit_price')->nullable();
                $table->bigInteger('subtotal')->default(0);
                $table->enum('status', ['requested', 'served'])->default('requested');
                $table->timestamps();

                $table->index(['jcode_id', 'status']);
            });
        } else {
            // Restaurer l'ENUM statut original
            DB::statement("ALTER TABLE jcodes MODIFY COLUMN statut ENUM('actif','utilise','expire') NOT NULL DEFAULT 'actif'");

            Schema::table('jcodes', function (Blueprint $table) {
                $table->dropColumn('montant_consomme');
            });

            // Restaurer l'ENUM status original des items
            DB::statement("ALTER TABLE jcode_items MODIFY COLUMN status ENUM('requested','served') NOT NULL DEFAULT 'requested'");

            Schema::table('jcode_items', function (Blueprint $table) {
                $table->dropForeign(['served_by_supplier_id']);
                $table->dropColumn(['served_by_supplier_id', 'quantity_served']);
            });
        }
    }
};
