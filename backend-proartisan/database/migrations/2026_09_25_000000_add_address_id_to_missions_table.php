<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'une mission à une adresse enregistrée du carnet d'adresses du client (Règle d'or 34).
 * La suppression ultérieure de l'adresse du carnet ne détruit pas la mission (ON DELETE SET NULL),
 * et les colonnes client_address, client_latitude, client_longitude conservent le snapshot immuable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('address_id')
                ->nullable()
                ->after('client_id')
                ->constrained('addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('address_id');
        });
    }
};
