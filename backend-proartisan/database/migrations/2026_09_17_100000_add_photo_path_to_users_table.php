<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo de profil de l'utilisateur, modifiable depuis le backoffice au même
 * titre que les pièces KYC. Stockée sur le disque privé comme les pièces KYC
 * (cf. KycDocument) : seule une URL signée à durée limitée y donne accès.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
