<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordonnées de l'adresse de livraison, figées sur la commande (Chantier 31).
 *
 * Le snapshot de 2026_09_19 ne gardait que le texte de l'adresse : la course,
 * le suivi et les tournées se rabattaient sur la position du compte du client,
 * qui n'est pas l'endroit où il se fait livrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'delivery_latitude')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('delivery_latitude', 10, 7)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_longitude')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('delivery_longitude', 10, 7)->nullable();
            });
        }

        // Commandes encore à livrer : leur course n'est pas réglée, elles
        // prennent les coordonnées actuelles de leur adresse. Une commande
        // close reste sans coordonnées plutôt que d'en recevoir de déduites
        // après coup.
        Order::query()
            ->where('delivery_mode', 'delivery')
            ->whereNotNull('address_id')
            ->whereNull('delivery_latitude')
            ->whereNotIn('status', ['delivered', 'cancelled', 'disputed'])
            ->with('address')
            ->lazyById(200)
            ->each(function (Order $order) {
                $frozen = Order::frozenDestination($order->address);
                if ($frozen['delivery_latitude'] !== null) {
                    $order->forceFill($frozen)->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        foreach (['delivery_latitude', 'delivery_longitude'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
