<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Même évolution que sur `parrainages` (voir
 * 2026_09_20_000004_add_invitation_fields_to_parrainages_table.php) pour le
 * parrainage client → client : permettre d'inviter un futur client qui n'a
 * pas encore de compte.
 *
 * Le statut `en_attente_inscription` est distinct du `en_attente` existant
 * (qui signifie « en attente de récompense / 1ère mission financée », pas
 * « en attente d'inscription ») : l'enum est élargi, sans toucher les
 * lignes déjà en `en_attente`/`recompense`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parrainages_clients', function (Blueprint $table) {
            $table->dropUnique(['filleul_id']);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('parrainages_clients', function (Blueprint $table) {
                $table->unsignedBigInteger('filleul_id')->nullable()->change();
            });
        } else {
            // MySQL 5.7 : ->change() nécessite doctrine/dbal, non installé.
            DB::statement('ALTER TABLE parrainages_clients MODIFY filleul_id BIGINT UNSIGNED NULL');
        }

        Schema::table('parrainages_clients', function (Blueprint $table) {
            $table->string('filleul_phone')->nullable()->after('filleul_id');
            $table->string('filleul_nom')->nullable()->after('filleul_phone');
        });

        // Sous-requête portable (MySQL et SQLite) plutôt qu'un
        // UPDATE ... INNER JOIN propre à MySQL.
        DB::statement('UPDATE parrainages_clients SET filleul_phone = (SELECT phone FROM users WHERE users.id = parrainages_clients.filleul_id) WHERE filleul_id IS NOT NULL');

        if (DB::getDriverName() === 'sqlite') {
            // Élargit l'enum (implémenté en CHECK constraint côté SQLite) :
            // les lignes déjà en 'en_attente'/'recompense' restent valides.
            Schema::table('parrainages_clients', function (Blueprint $table) {
                $table->enum('statut', ['en_attente_inscription', 'en_attente', 'recompense'])->default('en_attente')->change();
            });
        } else {
            // Élargit l'enum existant pour ajouter 'en_attente_inscription'
            // avant 'en_attente' : les lignes déjà en 'en_attente'/'recompense'
            // ne sont pas affectées, seule la liste des valeurs possibles
            // change.
            DB::statement("ALTER TABLE parrainages_clients MODIFY statut ENUM('en_attente_inscription', 'en_attente', 'recompense') NOT NULL DEFAULT 'en_attente'");
        }

        Schema::table('parrainages_clients', function (Blueprint $table) {
            $table->unique('filleul_phone');
        });
    }

    public function down(): void
    {
        Schema::table('parrainages_clients', function (Blueprint $table) {
            $table->dropUnique(['filleul_phone']);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('parrainages_clients', function (Blueprint $table) {
                $table->enum('statut', ['en_attente', 'recompense'])->default('en_attente')->change();
            });
        } else {
            DB::statement("ALTER TABLE parrainages_clients MODIFY statut ENUM('en_attente', 'recompense') NOT NULL DEFAULT 'en_attente'");
        }

        Schema::table('parrainages_clients', function (Blueprint $table) {
            $table->dropColumn(['filleul_phone', 'filleul_nom']);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('parrainages_clients', function (Blueprint $table) {
                $table->unsignedBigInteger('filleul_id')->nullable(false)->change();
            });
        } else {
            DB::statement('ALTER TABLE parrainages_clients MODIFY filleul_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('parrainages_clients', function (Blueprint $table) {
            $table->unique('filleul_id');
        });
    }
};
