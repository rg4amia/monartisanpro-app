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
        if (Schema::hasTable('missions') && ! Schema::hasColumn('missions', 'diagnostic_media_analysis')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->json('diagnostic_media_analysis')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('missions') && Schema::hasColumn('missions', 'diagnostic_media_analysis')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->dropColumn('diagnostic_media_analysis');
            });
        }
    }
};
