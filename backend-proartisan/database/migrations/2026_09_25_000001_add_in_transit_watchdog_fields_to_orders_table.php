<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('driver_picked_up_at')->nullable()->after('driver_assigned_at');
            $table->timestamp('driver_stalled_alert_at')->nullable()->after('driver_picked_up_at');
        });

        DB::table('settings')->insertOrIgnore([
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
            $table->dropColumn(['driver_picked_up_at', 'driver_stalled_alert_at']);
        });

        DB::table('settings')->where('key', 'driver_in_transit_timeout_minutes')->delete();
    }
};
