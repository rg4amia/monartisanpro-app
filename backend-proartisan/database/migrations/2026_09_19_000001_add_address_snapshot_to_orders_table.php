<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de l'adresse de livraison au moment de la commande : l'édition
 * ultérieure du carnet d'adresses (`addresses`) ne doit jamais réécrire
 * l'historique d'une commande déjà passée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('address_id')->nullable()->after('driver_id')->constrained('addresses')->nullOnDelete();
            $table->string('recipient_name', 150)->nullable()->after('address_id');
            $table->string('recipient_phone', 20)->nullable()->after('recipient_name');
            $table->string('delivery_address_line', 255)->nullable()->after('recipient_phone');
            $table->string('delivery_city', 100)->nullable()->after('delivery_address_line');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('address_id');
            $table->dropColumn(['recipient_name', 'recipient_phone', 'delivery_address_line', 'delivery_city']);
        });
    }
};
