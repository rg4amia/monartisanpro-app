<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course livreur « à la Yango » : le montant final (course + bonus d'attente)
 * est révélé à la livraison et payé par le client à ce moment-là.
 *
 * - `waiting_fee` : bonus d'attente cumulé, distinct du tarif de la course ;
 * - `delivery_fare_prepaid` : part déjà encaissée sous l'ancien modèle
 *   (course facturée à l'acceptation, ou prépayée dans un panier
 *   multi-quincailleries), déduite du montant demandé à la livraison ;
 * - `delivery_fare_status` : `a_payer` puis `paye`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'waiting_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('waiting_fee')->default(0);
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_fare_prepaid')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('delivery_fare_prepaid')->default(0);
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_fare_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('delivery_fare_status', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_fare_settled_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dateTime('delivery_fare_settled_at')->nullable();
            });
        }

        // Reprise de l'existant : courses déjà facturées à l'acceptation.
        DB::table('orders')
            ->where('delivery_mode', 'delivery')
            ->whereIn('status', ['driver_assigned', 'driver_picked_up', 'shipping'])
            ->where('delivery_fare_prepaid', 0)
            ->update(['delivery_fare_prepaid' => DB::raw('delivery_cost')]);

        // Paniers multi-quincailleries : course incluse dans le paiement groupé.
        DB::table('orders')
            ->where('delivery_mode', 'delivery')
            ->whereNotNull('order_group_id')
            ->whereIn('status', ['paid', 'prepared', 'searching_driver'])
            ->where('delivery_fare_prepaid', 0)
            ->update(['delivery_fare_prepaid' => DB::raw('delivery_cost')]);

        // Courses déjà livrées : réglées sous l'ancien modèle.
        DB::table('orders')
            ->where('delivery_mode', 'delivery')
            ->where('status', 'delivered')
            ->whereNull('delivery_fare_status')
            ->update(['delivery_fare_status' => 'paye']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        foreach (['waiting_fee', 'delivery_fare_prepaid', 'delivery_fare_status', 'delivery_fare_settled_at'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
