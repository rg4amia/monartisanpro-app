<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('requested_sector_id')
                ->nullable()
                ->after('artisan_id')
                ->constrained('sectors')
                ->nullOnDelete();

            $table->foreignId('requested_trade_id')
                ->nullable()
                ->after('requested_sector_id')
                ->constrained('trades')
                ->nullOnDelete();

            $table->decimal('client_latitude', 10, 8)
                ->nullable()
                ->after('ratio_materiaux');

            $table->decimal('client_longitude', 11, 8)
                ->nullable()
                ->after('client_latitude');

            $table->string('client_address')
                ->nullable()
                ->after('client_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropForeign(['requested_sector_id']);
            $table->dropForeign(['requested_trade_id']);
            $table->dropColumn([
                'requested_sector_id',
                'requested_trade_id',
                'client_latitude',
                'client_longitude',
                'client_address',
            ]);
        });
    }
};
