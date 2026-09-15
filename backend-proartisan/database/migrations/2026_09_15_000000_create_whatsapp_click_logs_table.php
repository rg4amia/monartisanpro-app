<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_click_logs', function (Blueprint $table) {
            $table->id();
            $table->string('page', 255)->nullable();
            $table->string('source', 50)->default('floating_button');
            $table->string('referrer', 255)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_click_logs');
    }
};
