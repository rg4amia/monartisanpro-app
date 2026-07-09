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
        Schema::create('score_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('event_type');
            $table->integer('points');
            $table->decimal('credibility_factor', 3, 2)->default(1.00);
            $table->foreignId('evaluation_id')->nullable()->constrained('evaluations')->onDelete('set null');
            $table->foreignId('mission_id')->nullable()->constrained('missions')->onDelete('set null');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_ledger_entries');
    }
};
