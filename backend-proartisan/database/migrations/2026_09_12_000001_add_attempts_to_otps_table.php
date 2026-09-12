<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur de tentatives de vérification par OTP.
 *
 * L'OTP est le mécanisme de connexion : sans compteur, un code à 4 chiffres
 * (10 000 combinaisons, valable 5 minutes) n'était protégé que par le throttle
 * par IP, contournable avec un parc de proxys. Le compteur rend l'attaque
 * inopérante quelle que soit l'IP, puisqu'il est porté par le code lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
