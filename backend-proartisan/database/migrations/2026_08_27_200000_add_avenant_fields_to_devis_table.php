<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->boolean('is_avenant')->default(false)->after('statut');
            $table->foreignId('parent_devis_id')->nullable()->after('is_avenant')->constrained('devis')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropForeign(['parent_devis_id']);
            $table->dropColumn(['is_avenant', 'parent_devis_id']);
        });
    }
};
