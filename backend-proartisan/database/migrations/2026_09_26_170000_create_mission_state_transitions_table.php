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
        if (! Schema::hasTable('mission_state_transitions')) {
            Schema::create('mission_state_transitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained('missions')->onDelete('cascade');
                $table->string('from_state', 50);
                $table->string('to_state', 50);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 255)->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['mission_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_state_transitions');
    }
};
