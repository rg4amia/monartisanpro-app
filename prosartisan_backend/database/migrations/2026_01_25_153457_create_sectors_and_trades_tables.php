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

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->nullable()->index();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->index();
            $table->string('name');
            $table->foreignId('sector_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['sector_id', 'name']);
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('trade_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropForeign(['trade_id']);
            $table->dropColumn('trade_id');
        });

        Schema::dropIfExists('trades');
        Schema::dropIfExists('sectors');
    }
};
