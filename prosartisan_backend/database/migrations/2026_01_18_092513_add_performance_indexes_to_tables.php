<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

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
            // Check and add indexes only if they don't exist
            if (!$this->indexExists('users', 'idx_users_type_status')) {
                $table->index(['user_type', 'account_status'], 'idx_users_type_status');
            }

            if (!$this->indexExists('users', 'users_failed_login_attempts_index')) {
                $table->index('failed_login_attempts');
            }

            if (!$this->indexExists('users', 'users_locked_until_index')) {
                $table->index('locked_until');
            }

            if (!$this->indexExists('users', 'idx_users_phone_type')) {
                $table->index(['phone_number', 'user_type'], 'idx_users_phone_type');
            }
        });


        // Add composite indexes for artisan_profiles table
        Schema::table('artisan_profiles', function (Blueprint $table) {
            if (!$this->indexExists('artisan_profiles', 'idx_artisan_category_kyc')) {
                $table->index(['trade_category', 'is_kyc_verified'], 'idx_artisan_category_kyc');
            }
        });

        // Add composite indexes for missions table
        Schema::table('missions', function (Blueprint $table) {
            if (!$this->indexExists('missions', 'idx_missions_status_category')) {
                $table->index(['status', 'trade_category'], 'idx_missions_status_category');
            }

            if (!$this->indexExists('missions', 'idx_missions_client_status')) {
                $table->index(['client_id', 'status'], 'idx_missions_client_status');
            }

            if (!$this->indexExists('missions', 'missions_budget_min_centimes_index')) {
                $table->index('budget_min_centimes');
            }

            if (!$this->indexExists('missions', 'missions_budget_max_centimes_index')) {
                $table->index('budget_max_centimes');
            }
        });

        // Add composite indexes for devis table
        Schema::table('devis', function (Blueprint $table) {
            if (!$this->indexExists('devis', 'idx_devis_mission_status')) {
                $table->index(['mission_id', 'status'], 'idx_devis_mission_status');
            }

            if (!$this->indexExists('devis', 'idx_devis_artisan_status')) {
                $table->index(['artisan_id', 'status'], 'idx_devis_artisan_status');
            }

            if (!$this->indexExists('devis', 'devis_total_amount_centimes_index')) {
                $table->index('total_amount_centimes');
            }
        });

        // Add composite indexes for sequestres table
        Schema::table('sequestres', function (Blueprint $table) {
            if (!$this->indexExists('sequestres', 'idx_sequestres_mission_status')) {
                $table->index(['mission_id', 'status'], 'idx_sequestres_mission_status');
            }

            if (!$this->indexExists('sequestres', 'sequestres_total_amount_centimes_index')) {
                $table->index('total_amount_centimes');
            }
            // Note: status index already exists from create_sequestres_table migration
        });

        // Add composite indexes for jetons_materiel table
        Schema::table('jetons_materiel', function (Blueprint $table) {
            if (!$this->indexExists('jetons_materiel', 'idx_jetons_artisan_status')) {
                $table->index(['artisan_id', 'status'], 'idx_jetons_artisan_status');
            }

            if (!$this->indexExists('jetons_materiel', 'idx_jetons_status_expires')) {
                $table->index(['status', 'expires_at'], 'idx_jetons_status_expires');
            }
            // Note: code, expires_at indexes already exist from create_jetons_materiel_table migration
        });

        // Add indexes for KYC verifications table
        Schema::table('kyc_verifications', function (Blueprint $table) {
            if (!$this->indexExists('kyc_verifications', 'idx_kyc_user_status')) {
                $table->index(['user_id', 'verification_status'], 'idx_kyc_user_status');
            }

            if (!$this->indexExists('kyc_verifications', 'kyc_verifications_verified_at_index')) {
                $table->index('verified_at');
            }
            // Note: verification_status index already exists from create_kyc_verifications_table migration
        });

        // Add indexes for reputation-related tables if they exist
        if (Schema::hasTable('reputation_profiles')) {
            Schema::table('reputation_profiles', function (Blueprint $table) {
                // Note: current_score and last_calculated_at indexes already exist from create_reputation_profiles_table migration
                // Only add additional composite indexes if needed

                if (!$this->indexExists('reputation_profiles', 'idx_reputation_score_range')) {
                    $table->index(['current_score', 'completed_projects'], 'idx_reputation_score_range');
                }
            });
        }

        if (Schema::hasTable('ratings')) {
            Schema::table('ratings', function (Blueprint $table) {
                if (!$this->indexExists('ratings', 'idx_ratings_rated_date')) {
                    // Composite index for rated user ratings by date
                    $table->index(['rated_id', 'created_at'], 'idx_ratings_rated_date');
                }

                // Note: score and created_at indexes already exist from create_ratings_table migration
            });
        }

        // Add indexes for dispute-related tables if they exist
        if (Schema::hasTable('litiges')) {
            Schema::table('litiges', function (Blueprint $table) {
                if (!$this->indexExists('litiges', 'idx_litiges_mission_status')) {
                    $table->index(['mission_id', 'status'], 'idx_litiges_mission_status');
                }

                if (!$this->indexExists('litiges', 'litiges_type_index')) {
                    $table->index('type');
                }

                if (!$this->indexExists('litiges', 'litiges_reporter_id_index')) {
                    $table->index('reporter_id');
                }

                if (!$this->indexExists('litiges', 'litiges_defendant_id_index')) {
                    $table->index('defendant_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop composite indexes for users table
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_type_status')) {
                $table->dropIndex('idx_users_type_status');
            }

            if ($this->indexExists('users', 'users_failed_login_attempts_index')) {
                $table->dropIndex(['failed_login_attempts']);
            }

            if ($this->indexExists('users', 'users_locked_until_index')) {
                $table->dropIndex(['locked_until']);
            }

            if ($this->indexExists('users', 'idx_users_phone_type')) {
                $table->dropIndex('idx_users_phone_type');
            }
        });

        // Drop composite indexes for artisan_profiles table
        Schema::table('artisan_profiles', function (Blueprint $table) {
            if ($this->indexExists('artisan_profiles', 'idx_artisan_category_kyc')) {
                $table->dropIndex('idx_artisan_category_kyc');
            }
        });

        // Drop composite indexes for missions table
        Schema::table('missions', function (Blueprint $table) {
            if ($this->indexExists('missions', 'idx_missions_status_category')) {
                $table->dropIndex('idx_missions_status_category');
            }

            if ($this->indexExists('missions', 'idx_missions_client_status')) {
                $table->dropIndex('idx_missions_client_status');
            }

            if ($this->indexExists('missions', 'missions_budget_min_centimes_index')) {
                $table->dropIndex(['budget_min_centimes']);
            }

            if ($this->indexExists('missions', 'missions_budget_max_centimes_index')) {
                $table->dropIndex(['budget_max_centimes']);
            }
        });

        // Drop composite indexes for devis table
        Schema::table('devis', function (Blueprint $table) {
            if ($this->indexExists('devis', 'idx_devis_mission_status')) {
                $table->dropIndex('idx_devis_mission_status');
            }

            if ($this->indexExists('devis', 'idx_devis_artisan_status')) {
                $table->dropIndex('idx_devis_artisan_status');
            }

            if ($this->indexExists('devis', 'devis_total_amount_centimes_index')) {
                $table->dropIndex(['total_amount_centimes']);
            }
        });

        // Drop composite indexes for sequestres table
        Schema::table('sequestres', function (Blueprint $table) {
            if ($this->indexExists('sequestres', 'idx_sequestres_mission_status')) {
                $table->dropIndex('idx_sequestres_mission_status');
            }

            if ($this->indexExists('sequestres', 'sequestres_total_amount_centimes_index')) {
                $table->dropIndex(['total_amount_centimes']);
            }
            // Note: status index is owned by create_sequestres_table migration
        });

        // Drop composite indexes for jetons_materiel table
        Schema::table('jetons_materiel', function (Blueprint $table) {
            if ($this->indexExists('jetons_materiel', 'idx_jetons_artisan_status')) {
                $table->dropIndex('idx_jetons_artisan_status');
            }

            if ($this->indexExists('jetons_materiel', 'idx_jetons_status_expires')) {
                $table->dropIndex('idx_jetons_status_expires');
            }
            // Note: code, expires_at indexes are owned by create_jetons_materiel_table migration
        });

        // Drop indexes for KYC verifications table
        Schema::table('kyc_verifications', function (Blueprint $table) {
            if ($this->indexExists('kyc_verifications', 'idx_kyc_user_status')) {
                $table->dropIndex('idx_kyc_user_status');
            }

            if ($this->indexExists('kyc_verifications', 'kyc_verifications_verified_at_index')) {
                $table->dropIndex(['verified_at']);
            }
            // Note: verification_status index is owned by create_kyc_verifications_table migration
        });

        // Drop indexes for reputation-related tables if they exist
        if (Schema::hasTable('reputation_profiles')) {
            Schema::table('reputation_profiles', function (Blueprint $table) {
                if ($this->indexExists('reputation_profiles', 'idx_reputation_score_range')) {
                    $table->dropIndex('idx_reputation_score_range');
                }
                // Note: current_score and last_calculated_at indexes are owned by create_reputation_profiles_table migration
            });
        }

        if (Schema::hasTable('ratings')) {
            Schema::table('ratings', function (Blueprint $table) {
                if ($this->indexExists('ratings', 'idx_ratings_rated_date')) {
                    $table->dropIndex('idx_ratings_rated_date');
                }

                // Note: score and created_at indexes are owned by create_ratings_table migration
            });
        }

        // Drop indexes for dispute-related tables if they exist
        if (Schema::hasTable('litiges')) {
            Schema::table('litiges', function (Blueprint $table) {
                if ($this->indexExists('litiges', 'idx_litiges_mission_status')) {
                    $table->dropIndex('idx_litiges_mission_status');
                }

                if ($this->indexExists('litiges', 'litiges_type_index')) {
                    $table->dropIndex(['type']);
                }

                if ($this->indexExists('litiges', 'litiges_reporter_id_index')) {
                    $table->dropIndex(['reporter_id']);
                }

                if ($this->indexExists('litiges', 'litiges_defendant_id_index')) {
                    $table->dropIndex(['defendant_id']);
                }
            });
        }
    }
};
