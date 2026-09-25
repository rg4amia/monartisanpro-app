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
        if (Schema::hasTable('credit_applications')) {
            Schema::table('credit_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('credit_applications', 'repaid_amount')) {
                    $table->bigInteger('repaid_amount')->default(0);
                }
                if (! Schema::hasColumn('credit_applications', 'repaid_at')) {
                    $table->timestamp('repaid_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('credit_applications')) {
            Schema::table('credit_applications', function (Blueprint $table) {
                if (Schema::hasColumn('credit_applications', 'repaid_at')) {
                    $table->dropColumn('repaid_at');
                }
                if (Schema::hasColumn('credit_applications', 'repaid_amount')) {
                    $table->dropColumn('repaid_amount');
                }
            });
        }
    }
};
