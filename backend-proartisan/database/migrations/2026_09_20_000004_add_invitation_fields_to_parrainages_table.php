<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permet d'inviter par téléphone un futur artisan qui n'a pas encore de
 * compte (Règle d'or 35 : le parrain saisit nom + téléphone, un SMS
 * d'invitation part, la liaison se fait automatiquement à l'inscription).
 *
 * `filleul_id` devient nullable (ligne d'invitation en attente tant que le
 * filleul n'existe pas) et `filleul_phone` porte désormais l'unicité :
 * un même numéro ne peut être parrainé/invité qu'une seule fois, qu'il
 * corresponde déjà à un compte ou non.
 *
 * MySQL 5.7 : ->change() nécessite doctrine/dbal, non installé sur ce
 * projet -> ALTER MODIFY en SQL brut. SQLite (suite de tests) applique le
 * changement nativement via Schema::change() (pas de doctrine/dbal requis
 * depuis Laravel 11), suivant le précédent déjà posé par la migration
 * 2026_09_16_000003_convert_transactions_type_to_varchar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parrainages', function (Blueprint $table) {
            $table->dropUnique(['filleul_id']);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('parrainages', function (Blueprint $table) {
                $table->unsignedBigInteger('filleul_id')->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE parrainages MODIFY filleul_id BIGINT UNSIGNED NULL');
        }

        Schema::table('parrainages', function (Blueprint $table) {
            $table->string('filleul_phone')->nullable()->after('filleul_id');
            $table->string('filleul_nom')->nullable()->after('filleul_phone');
            $table->enum('statut', ['en_attente_inscription', 'actif'])->default('actif')->after('filleul_nom');
        });

        // Backfill : toutes les lignes existantes ont déjà un filleul_id
        // résolu → statut reste 'actif' (défaut), on renseigne filleul_phone
        // depuis users.phone pour cohérence. Sous-requête portable (MySQL et
        // SQLite), contrairement à un UPDATE ... INNER JOIN propre à MySQL.
        DB::statement('UPDATE parrainages SET filleul_phone = (SELECT phone FROM users WHERE users.id = parrainages.filleul_id) WHERE filleul_id IS NOT NULL');

        Schema::table('parrainages', function (Blueprint $table) {
            $table->unique('filleul_phone');
        });
    }

    public function down(): void
    {
        Schema::table('parrainages', function (Blueprint $table) {
            $table->dropUnique(['filleul_phone']);
            $table->dropColumn(['filleul_phone', 'filleul_nom', 'statut']);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('parrainages', function (Blueprint $table) {
                $table->unsignedBigInteger('filleul_id')->nullable(false)->change();
            });
        } else {
            DB::statement('ALTER TABLE parrainages MODIFY filleul_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('parrainages', function (Blueprint $table) {
            $table->unique('filleul_id');
        });
    }
};
