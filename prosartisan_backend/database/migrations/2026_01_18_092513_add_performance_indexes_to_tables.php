<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add additional performance indexes for frequently queried fields
     * Requirements: 17.5
     */
    public function up(): void
    {
        // Add composite indexes for users table
        Schema::table('users', function (Blueprint $table) {
            // Composite index for active users by type
            $table->index(['user_type', 'account_status'], 'idx_users_type_status');
            // Index for failed login attempts (security queries)
            $table->index('failed_login_attempts');
            // Index for locked accounts
            $table->index('locked_until');
            // Composite index for phone number lookups
            $table->index(['phone_number', 'user_type'], 'idx_users_phone_type');
        });

        // Add composite indexes for artisan_profiles table
        Schema::table('artisan_profiles', function (Blueprint $table) {
            // Composite index for KYC verified artisans by category
            $table->index(['trade_category', 'is_kyc_verified'], 'idx_artisan_category_kyc');
            // Index for KYC verification status
            $table->index('is_kyc_verified');
        });

        // Add composite indexes for missions table
        Schema::table('missions', function (Blueprint $table) {
            // Composite index for open missions by category
            $table->index(['status', 'trade_category'], 'idx_missions_status_category');
            // Composite index for client missions by status
            $table->index(['client_id', 'status'], 'idx_missions_client_status');
            // Index for budget range queries
            $table->index('budget_min_centimes');
            $table->index('budget_max_centimes');
        });

        // Add composite indexes for devis table
        Schema::table('devis', function (Blueprint $table) {
            // Composite index for mission quotes by status
            $table->index(['mission_id', 'status'], 'idx_devis_mission_status');
            // Composite index for artisan quotes by status
            $table->index(['artisan_id', 'status'], 'idx_devis_artisan_status');
            // Index for amount-based queries
            $table->index('total_amount_centimes');
        });

        // Add composite indexes for sequestres table
        Schema::table('sequestres', function (Blueprint $table) {
            // Index for status-based queries
            $table->index('status');
            // Composite index for mission escrow status
            $table->index(['mission_id', 'status'], 'idx_sequestres_mission_status');
            // Index for amount-based queries
            $table->index('total_amount_centimes');
        });

        // Add composite indexes for jetons_materiel table
        Schema::table('jetons_materiel', function (Blueprint $table) {
            // Index for jeton code lookups (unique but add explicit index)
            $table->index('code');
            // Composite index for artisan active jetons
            $table->index(['artisan_id', 'status'], 'idx_jetons_artisan_status');
            // Index for expiration-based queries
            $table->index('expires_at');
            // Composite index for expired jetons cleanup
            $table->index(['status', 'expires_at'], 'idx_jetons_status_expires');
        });

        // Add indexes for KYC verifications table
        Schema::table('kyc_verifications', function (Blueprint $table) {
            // Composite index for user verification status
            $table->index(['user_id', 'verification_status'], 'idx_kyc_user_status');
            // Index for verification status queries
            $table->index('verification_status');
            // Index for verified date queries
            $table->index('verified_at');
        });

        // Add indexes for reputation-related tables if they exist
        if (Schema::hasTable('reputation_profiles')) {
            Schema::table('reputation_profiles', function (Blueprint $table) {
                // Index for score-based queries
                $table->index('current_score');
                // Index for last calculation time
                $table->index('last_calculated_at');
            });
        }

        if (Schema::hasTable('ratings')) {
            Schema::table('ratings', function (Blueprint $table) {
                // Composite index for artisan ratings
                $table->index(['artisan_id', 'created_at'], 'idx_ratings_artisan_date');
                // Index for rating values
                $table->index('rating_value');
            });
        }

        // Add indexes for dispute-related tables if they exist
        if (Schema::hasTable('litiges')) {
            Schema::table('litiges', function (Blueprint $table) {
                // Composite index for mission disputes
                $table->index(['mission_id', 'status'], 'idx_litiges_mission_status');
                // Index for dispute type
                $table->index('type');
                // Index for reporter
                $table->index('reporter_id');
                // Index for defendant
                $table->index('defendant_id');
            });
        }

        // Add additional PostGIS spatial indexes for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            // Ensure all geography columns have proper spatial indexes
            $spatialIndexes = [
                'artisan_profiles' => 'location',
                'fournisseur_profiles' => 'shop_location',
                'referent_zone_profiles' => 'coverage_area',
                'missions' => 'location'
            ];

            foreach ($spatialIndexes as $table => $column) {
                if (Schema::hasTable($table)) {
                    // Check if index doesn't already exist
                    $indexName = "idx_{$table}_{$column}_gist";
                    $existingIndex = DB::select("
                        SELECT indexname
                        FROM pg_indexes
                        WHERE tablename = ? AND indexname = ?
                    ", [$table, $indexName]);

                    if (empty($existingIndex)) {
                        DB::statement("CREATE INDEX {$indexName} ON {$table} USING GIST({$column})");
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop composite indexes for users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_type_status');
            $table->dropIndex(['failed_login_attempts']);
            $table->dropIndex(['locked_until']);
            $table->dropIndex('idx_users_phone_type');
        });

        // Drop composite indexes for artisan_profiles table
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_artisan_category_kyc');
            $table->dropIndex(['is_kyc_verified']);
        });

        // Drop composite indexes for missions table
        Schema::table('missions', function (Blueprint $table) {
            $table->dropIndex('idx_missions_status_category');
            $table->dropIndex('idx_missions_client_status');
            $table->dropIndex(['budget_min_centimes']);
            $table->dropIndex(['budget_max_centimes']);
        });

        // Drop composite indexes for devis table
        Schema::table('devis', function (Blueprint $table) {
            $table->dropIndex('idx_devis_mission_status');
            $table->dropIndex('idx_devis_artisan_status');
            $table->dropIndex(['total_amount_centimes']);
        });

        // Drop composite indexes for sequestres table
        Schema::table('sequestres', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex('idx_sequestres_mission_status');
            $table->dropIndex(['total_amount_centimes']);
        });

        // Drop composite indexes for jetons_materiel table
        Schema::table('jetons_materiel', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropIndex('idx_jetons_artisan_status');
            $table->dropIndex(['expires_at']);
            $table->dropIndex('idx_jetons_status_expires');
        });

        // Drop indexes for KYC verifications table
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->dropIndex('idx_kyc_user_status');
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['verified_at']);
        });

        // Drop indexes for reputation-related tables if they exist
        if (Schema::hasTable('reputation_profiles')) {
            Schema::table('reputation_profiles', function (Blueprint $table) {
                $table->dropIndex(['current_score']);
                $table->dropIndex(['last_calculated_at']);
            });
        }

        if (Schema::hasTable('ratings')) {
            Schema::table('ratings', function (Blueprint $table) {
                $table->dropIndex('idx_ratings_artisan_date');
                $table->dropIndex(['rating_value']);
            });
        }

        // Drop indexes for dispute-related tables if they exist
        if (Schema::hasTable('litiges')) {
            Schema::table('litiges', function (Blueprint $table) {
                $table->dropIndex('idx_litiges_mission_status');
                $table->dropIndex(['type']);
                $table->dropIndex(['reporter_id']);
                $table->dropIndex(['defendant_id']);
            });
        }

        // Drop additional PostGIS spatial indexes for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            $spatialIndexes = [
                'artisan_profiles' => 'location',
                'fournisseur_profiles' => 'shop_location',
                'referent_zone_profiles' => 'coverage_area',
                'missions' => 'location'
            ];

            foreach ($spatialIndexes as $table => $column) {
                if (Schema::hasTable($table)) {
                    $indexName = "idx_{$table}_{$column}_gist";
                    DB::statement("DROP INDEX IF EXISTS {$indexName}");
                }
            }
        }
    }
};
