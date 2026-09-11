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
        Schema::table('jalons', function (Blueprint $table) {
            $table->unsignedTinyInteger('conformity_score')->nullable()->after('photos_json'); // 0 à 100
            $table->json('vision_analysis_json')->nullable()->after('conformity_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jalons', function (Blueprint $table) {
            $table->dropColumn(['conformity_score', 'vision_analysis_json']);
        });
    }
};
