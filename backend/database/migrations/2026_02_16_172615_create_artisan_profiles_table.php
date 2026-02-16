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
        Schema::create('artisan_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trade_id')->constrained();
            $table->geometry('location', 'point'); // MySQL POINT type for geolocation (NOT NULL required for spatial index)
            $table->string('zone_name', 100)->nullable();
            $table->text('bio')->nullable();
            $table->integer('experience_years')->default(0);
            $table->boolean('available')->default(true);
            $table->timestamps();

            // MySQL spatial index for performance (requires NOT NULL column)
            $table->spatialIndex('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artisan_profiles');
    }
};
