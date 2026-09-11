<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['text', 'image', 'audio'])->default('text');
            $table->text('content')->nullable();
            $table->string('media_url', 500)->nullable();
            $table->json('media_metadata')->nullable();
            $table->boolean('is_redacted')->default(false);
            $table->boolean('flagged_for_review')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'created_at']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_messages');
    }
};
