<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->boolean('intervient_la_nuit')
                ->default(false)
                ->after('photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropColumn('intervient_la_nuit');
        });
    }
};
