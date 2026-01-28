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
        Schema::create('mediation_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('litige_id');
            $table->uuid('sender_id');
            $table->text('message');
            $table->timestamp('created_at');

            $table->foreign('litige_id')->references('id')->on('litiges')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['litige_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mediation_communications');
    }
};
