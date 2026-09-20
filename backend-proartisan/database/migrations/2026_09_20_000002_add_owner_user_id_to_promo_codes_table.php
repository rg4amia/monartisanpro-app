<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un code promo peut désormais appartenir à un utilisateur précis (ex : code
 * de récompense de parrainage client) : seul son propriétaire peut l'appliquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('code')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};
