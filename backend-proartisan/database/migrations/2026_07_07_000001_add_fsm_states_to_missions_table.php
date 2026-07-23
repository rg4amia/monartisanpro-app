<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive : ajoute les nouveaux statuts de la machine à états
 * formelle (Epic 9 du backlog Scrum) à la table missions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // MySQL 5.7 : modification d'ENUM via ALTER TABLE
            DB::statement("
                ALTER TABLE missions
                MODIFY COLUMN status ENUM(
                    'en_attente',
                    'financee',
                    'en_cours',
                    'terminee',
                    'litige',
                    'draft',
                    'pending_funding',
                    'funded_locked',
                    'in_progress',
                    'pending_approval',
                    'completed',
                    'disputed'
                ) NOT NULL DEFAULT 'draft'
            ");
        }

        // Migrer les données existantes vers les nouveaux statuts
        DB::statement("UPDATE missions SET status = 'draft'        WHERE status = 'en_attente'");
        DB::statement("UPDATE missions SET status = 'funded_locked' WHERE status = 'financee'");
        DB::statement("UPDATE missions SET status = 'in_progress'  WHERE status = 'en_cours'");
        DB::statement("UPDATE missions SET status = 'completed'    WHERE status = 'terminee'");
        DB::statement("UPDATE missions SET status = 'disputed'     WHERE status = 'litige'");

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE missions
                MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'draft'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE missions
                MODIFY COLUMN status ENUM(
                    'draft',
                    'pending_funding',
                    'funded_locked',
                    'in_progress',
                    'pending_approval',
                    'completed',
                    'disputed',
                    'en_attente',
                    'financee',
                    'en_cours',
                    'terminee',
                    'litige'
                ) NOT NULL DEFAULT 'draft'
            ");
        }

        DB::statement("UPDATE missions SET status = 'en_attente' WHERE status = 'draft'");
        DB::statement("UPDATE missions SET status = 'financee'   WHERE status = 'funded_locked'");
        DB::statement("UPDATE missions SET status = 'en_cours'   WHERE status = 'in_progress'");
        DB::statement("UPDATE missions SET status = 'terminee'   WHERE status = 'completed'");
        DB::statement("UPDATE missions SET status = 'litige'     WHERE status = 'disputed'");

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE missions
                MODIFY COLUMN status ENUM(
                    'en_attente',
                    'financee',
                    'en_cours',
                    'terminee',
                    'litige'
                ) NOT NULL DEFAULT 'en_attente'
            ");
        }
    }
};
