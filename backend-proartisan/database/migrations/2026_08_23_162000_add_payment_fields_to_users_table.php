<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'payment_phone')) {
                $table->string('payment_phone', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'preferred_payment_provider')) {
                $table->string('preferred_payment_provider', 20)->nullable()->after('payment_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payment_phone', 'preferred_payment_provider']);
        });
    }
};
