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
        Schema::table('transactions', function (Blueprint $table) {
            // Champs pour Wave CI
            $table->string('wave_checkout_id', 100)->nullable()->after('reference_externe');
            $table->string('wave_payment_id', 100)->nullable()->after('wave_checkout_id');
            $table->string('wave_client_reference', 100)->nullable()->after('wave_payment_id');

            // Champs pour Orange Money CI
            $table->string('orange_order_id', 100)->nullable()->after('wave_client_reference');
            $table->string('orange_payment_token', 255)->nullable()->after('orange_order_id');
            $table->string('orange_tx_reference', 100)->nullable()->after('orange_payment_token');

            // Champs communs
            $table->text('webhook_payload')->nullable()->after('orange_tx_reference');
            $table->timestamp('paid_at')->nullable()->after('webhook_payload');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->text('error_message')->nullable()->after('failed_at');
            $table->string('client_phone', 20)->nullable()->after('error_message');
            $table->json('metadata')->nullable()->after('client_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'wave_checkout_id',
                'wave_payment_id',
                'wave_client_reference',
                'orange_order_id',
                'orange_payment_token',
                'orange_tx_reference',
                'webhook_payload',
                'paid_at',
                'failed_at',
                'error_message',
                'client_phone',
                'metadata',
            ]);
        });
    }
};
