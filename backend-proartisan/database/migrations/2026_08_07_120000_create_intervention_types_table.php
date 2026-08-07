<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('intervention_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('requires_labor')->default(true);
            $table->timestamps();
        });

        // Seed default values
        DB::table('intervention_types')->insert([
            ['name' => 'Maintenance', 'requires_labor' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Assistance', 'requires_labor' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dépannage', 'requires_labor' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_types');
    }
};
