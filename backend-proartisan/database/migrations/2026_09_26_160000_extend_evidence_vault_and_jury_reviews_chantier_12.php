<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 12 — Coffre-fort des preuves universel (Evidence Vault) et
 * moteur d'arbitrage par les pairs (Jury ProsArtisan).
 *
 * Règle d'or 55 : vérification colonne par colonne pour MariaDB 11.8.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Extension de la table evidence_vault
        if (Schema::hasTable('evidence_vault')) {
            // Rendre litige_id nullable si présent
            Schema::table('evidence_vault', function (Blueprint $table) {
                if (Schema::hasColumn('evidence_vault', 'litige_id')) {
                    $table->unsignedBigInteger('litige_id')->nullable()->change();
                }
                if (! Schema::hasColumn('evidence_vault', 'mission_id')) {
                    $table->foreignId('mission_id')->nullable()->after('id')->constrained('missions')->nullOnDelete();
                }
                if (! Schema::hasColumn('evidence_vault', 'jalon_id')) {
                    $table->foreignId('jalon_id')->nullable()->after('mission_id')->constrained('jalons')->nullOnDelete();
                }
                if (! Schema::hasColumn('evidence_vault', 'evidence_type')) {
                    $table->string('evidence_type', 30)->default('litige')->after('jalon_id');
                }
                if (! Schema::hasColumn('evidence_vault', 'file_path')) {
                    $table->string('file_path')->nullable()->after('file_url');
                }
                if (! Schema::hasColumn('evidence_vault', 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable()->after('file_path');
                }
                if (! Schema::hasColumn('evidence_vault', 'mime_type')) {
                    $table->string('mime_type', 100)->nullable()->after('file_size');
                }
                if (! Schema::hasColumn('evidence_vault', 'device_fingerprint')) {
                    $table->string('device_fingerprint', 100)->nullable()->after('ip_address');
                }
                if (! Schema::hasColumn('evidence_vault', 'gps_lat')) {
                    $table->decimal('gps_lat', 10, 7)->nullable()->after('device_fingerprint');
                }
                if (! Schema::hasColumn('evidence_vault', 'gps_lng')) {
                    $table->decimal('gps_lng', 10, 7)->nullable()->after('gps_lat');
                }
                if (! Schema::hasColumn('evidence_vault', 'is_tampered')) {
                    $table->boolean('is_tampered')->default(false)->after('gps_lng');
                }
                if (! Schema::hasColumn('evidence_vault', 'tampered_detected_at')) {
                    $table->timestamp('tampered_detected_at')->nullable()->after('is_tampered');
                }
            });
        }

        // 2. Extension de la table jury_reviews
        if (Schema::hasTable('jury_reviews')) {
            Schema::table('jury_reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('jury_reviews', 'status')) {
                    $table->string('status', 20)->default('assigned')->after('jure_id');
                }
                if (! Schema::hasColumn('jury_reviews', 'split_artisan_percentage')) {
                    $table->unsignedTinyInteger('split_artisan_percentage')->nullable()->after('verdict');
                }
                if (! Schema::hasColumn('jury_reviews', 'technical_comment')) {
                    $table->text('technical_comment')->nullable()->after('split_artisan_percentage');
                }
                if (! Schema::hasColumn('jury_reviews', 'compensation_paid')) {
                    $table->boolean('compensation_paid')->default(false)->after('compensation');
                }
                if (! Schema::hasColumn('jury_reviews', 'compensation_paid_at')) {
                    $table->timestamp('compensation_paid_at')->nullable()->after('compensation_paid');
                }
                if (! Schema::hasColumn('jury_reviews', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->after('compensation_paid_at');
                }
                if (! Schema::hasColumn('jury_reviews', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('assigned_at');
                }
            });
        }

        // 3. Extension de la table litiges pour le statut du Jury
        if (Schema::hasTable('litiges')) {
            Schema::table('litiges', function (Blueprint $table) {
                if (! Schema::hasColumn('litiges', 'jury_status')) {
                    $table->string('jury_status', 30)->default('none')->after('workflow_step');
                }
                if (! Schema::hasColumn('litiges', 'jury_consensus')) {
                    $table->string('jury_consensus', 30)->nullable()->after('jury_status');
                }
                if (! Schema::hasColumn('litiges', 'jury_recommended_split')) {
                    $table->unsignedTinyInteger('jury_recommended_split')->nullable()->after('jury_consensus');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('evidence_vault')) {
            Schema::table('evidence_vault', function (Blueprint $table) {
                $columns = [
                    'mission_id', 'jalon_id', 'evidence_type', 'file_path', 'file_size',
                    'mime_type', 'device_fingerprint', 'gps_lat', 'gps_lng', 'is_tampered',
                    'tampered_detected_at',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('evidence_vault', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('jury_reviews')) {
            Schema::table('jury_reviews', function (Blueprint $table) {
                $columns = [
                    'status', 'split_artisan_percentage', 'technical_comment',
                    'compensation_paid', 'compensation_paid_at', 'assigned_at', 'expires_at',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('jury_reviews', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('litiges')) {
            Schema::table('litiges', function (Blueprint $table) {
                $columns = ['jury_status', 'jury_consensus', 'jury_recommended_split'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('litiges', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
