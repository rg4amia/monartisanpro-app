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
        Schema::table('devis', function (Blueprint $table) {
            $table->boolean('materials_required')->default(true)->after('statut');
            $table->foreignId('intervention_type_id')->nullable()->after('materials_required')->constrained('intervention_types')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropForeign(['intervention_type_id']);
            $table->dropColumn(['materials_required', 'intervention_type_id']);
        });
    }
};
