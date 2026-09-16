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
            if (!Schema::hasColumn('orders', 'order_group_id')) {
                $table->string('order_group_id', 64)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('orders', 'is_parent_group')) {
                $table->boolean('is_parent_group')->default(false)->after('order_group_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_group_id')) {
                $table->dropIndex(['order_group_id']);
                $table->dropColumn('order_group_id');
            }
            if (Schema::hasColumn('orders', 'is_parent_group')) {
                $table->dropColumn('is_parent_group');
            }
        });
    }
};
