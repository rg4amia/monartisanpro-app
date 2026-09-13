<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diffusion de contenus vocaux et vidéo dans le module Communication.
 *
 * `type` était un ENUM('annonce','le_saviez_vous'). MySQL le rejetterait,
 * et SQLite aussi via la contrainte CHECK que Laravel génère pour un enum :
 * les tests tourneraient donc verts en local et l'insertion casserait en
 * production, ou l'inverse. On bascule en VARCHAR, comme l'a fait
 * `missions.status` lors du passage à la machine à états.
 *
 * L'audio est téléversé (`media_path`, disque public : une annonce diffusée
 * à tout un rôle n'a rien de confidentiel, et le servir par le serveur web
 * évite un processus PHP par lecture). La vidéo est référencée par une URL
 * externe (`media_external_url`) : héberger de la vidéo sur un mutualisé
 * épuiserait quota disque et bande passante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('communications', function (Blueprint $table) {
                $table->string('type', 32)->default('annonce')->change();
            });
        } else {
            DB::statement("ALTER TABLE communications MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'annonce'");
        }

        Schema::table('communications', function (Blueprint $table) {
            // Chemin sur le disque public — renseigné pour un contenu vocal.
            $table->string('media_path')->nullable()->after('contenu');

            // URL de la plateforme d'hébergement — renseignée pour une vidéo.
            $table->string('media_external_url', 2048)->nullable()->after('media_path');

            $table->string('media_mime', 128)->nullable()->after('media_external_url');

            // Octets et secondes : affichés avant lecture pour que l'utilisateur
            // sache ce qu'il va consommer avant de dépenser son forfait.
            $table->unsignedBigInteger('media_size')->nullable()->after('media_mime');
            $table->unsignedInteger('media_duration')->nullable()->after('media_size');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn([
                'media_path',
                'media_external_url',
                'media_mime',
                'media_size',
                'media_duration',
            ]);
        });

        // Les publications vocales et vidéo n'ont plus de type valide : on les
        // ramène au type par défaut plutôt que de les perdre.
        DB::table('communications')
            ->whereIn('type', ['audio', 'video'])
            ->update(['type' => 'annonce']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE communications MODIFY COLUMN type ENUM('annonce','le_saviez_vous') NOT NULL DEFAULT 'annonce'");
        }
    }
};
