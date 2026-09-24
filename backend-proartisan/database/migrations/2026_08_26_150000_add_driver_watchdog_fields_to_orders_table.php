<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'driver_assigned_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->timestamp('driver_assigned_at')->nullable();
                if (Schema::hasColumn('orders', 'driver_id')) {
                    $column->after('driver_id');
                }
            });
        }

        if (! Schema::hasColumn('orders', 'driver_reassignment_count')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->unsignedTinyInteger('driver_reassignment_count')->default(0);
                if (Schema::hasColumn('orders', 'driver_assigned_at')) {
                    $column->after('driver_assigned_at');
                }
            });
        }

        // Settings configurables pour le watchdog livreur
        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'driver_watchdog_timeout_minutes',
                'value' => '15',
                'type' => 'integer',
                'group' => 'logistique',
                'label' => 'Délai watchdog livreur (minutes)',
                'description' => 'Durée d\'inactivité du livreur après acceptation de la course avant réaffectation automatique.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'driver_max_reassignments',
                'value' => '3',
                'type' => 'integer',
                'group' => 'logistique',
                'label' => 'Réaffectations max par commande',
                'description' => 'Nombre maximum de réaffectations automatiques de livreur par commande avant escalade admin.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['driver_assigned_at', 'driver_reassignment_count']);
        });

        DB::table('settings')->whereIn('key', [
            'driver_watchdog_timeout_minutes',
            'driver_max_reassignments',
        ])->delete();
    }
};
