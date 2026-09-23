<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `score_prosartisan_at_application` est resté un TINYINT signé (max 127) de
 * l'époque de l'échelle 0–100 : il n'a été que renommé lors du passage à
 * l'échelle 0–1000. Un artisan éligible au micro-crédit ayant un score
 * >= 700, toute demande échouait en production (MariaDB strict : valeur hors
 * limites), le contrôleur la transformant en HTTP 422. SQLite, qui ignore les
 * tailles de colonnes, masquait le défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('score_prosartisan_at_application')->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->tinyInteger('score_prosartisan_at_application')->change();
        });
    }
};
