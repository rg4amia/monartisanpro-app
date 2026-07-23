<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('reception_code');
            $table->string('pickup_photo_url')->nullable()->after('delivered_at');
            $table->string('delivery_photo_url')->nullable()->after('pickup_photo_url');
            $table->integer('waiting_time_minutes')->default(0)->after('delivery_photo_url');
            $table->text('dispute_reason')->nullable()->after('waiting_time_minutes');
            $table->timestamp('dispute_opened_at')->nullable()->after('dispute_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_at',
                'pickup_photo_url',
                'delivery_photo_url',
                'waiting_time_minutes',
                'dispute_reason',
                'dispute_opened_at',
            ]);
        });
    }
};
