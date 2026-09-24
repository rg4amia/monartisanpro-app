<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // S'assurer defensivement que les colonnes du watchdog precedent existent
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

        // Ajouter les colonnes du watchdog in-transit
        if (! Schema::hasColumn('orders', 'driver_picked_up_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->timestamp('driver_picked_up_at')->nullable();
                if (Schema::hasColumn('orders', 'driver_reassignment_count')) {
                    $column->after('driver_reassignment_count');
                } elseif (Schema::hasColumn('orders', 'driver_assigned_at')) {
                    $column->after('driver_assigned_at');
                } elseif (Schema::hasColumn('orders', 'driver_id')) {
                    $column->after('driver_id');
                }
            });
        }

        if (! Schema::hasColumn('orders', 'driver_stalled_alert_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $column = $table->timestamp('driver_stalled_alert_at')->nullable();
                if (Schema::hasColumn('orders', 'driver_picked_up_at')) {
                    $column->after('driver_picked_up_at');
                }
            });
        }

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
            [
                'key' => 'driver_in_transit_timeout_minutes',
                'value' => '25',
                'type' => 'integer',
                'group' => 'logistique',
                'label' => 'Délai d\'inactivité en transit (minutes)',
                'description' => 'Durée d\'inactivité GPS du livreur après retrait des matériaux avant relance et alerte admin.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $toDrop = [];
            if (Schema::hasColumn('orders', 'driver_stalled_alert_at')) {
                $toDrop[] = 'driver_stalled_alert_at';
            }
            if (Schema::hasColumn('orders', 'driver_picked_up_at')) {
                $toDrop[] = 'driver_picked_up_at';
            }
            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        DB::table('settings')->where('key', 'driver_in_transit_timeout_minutes')->delete();
    }
};
