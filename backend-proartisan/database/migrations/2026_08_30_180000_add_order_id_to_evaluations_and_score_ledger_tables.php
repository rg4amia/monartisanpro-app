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
        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignId('mission_id')->nullable()->change();
            $table->foreignId('order_id')->nullable()->after('mission_id')->constrained('orders')->cascadeOnDelete();
        });

        Schema::table('score_ledger_entries', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('mission_id')->constrained('orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('score_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->foreignId('mission_id')->nullable(false)->change();
        });
    }
};
