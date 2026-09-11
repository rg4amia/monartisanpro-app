<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_realtime_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->string('event_type', 50); // 'mission_status', 'jalon_updated', 'jcode_scanned', 'chat_message', 'delivery_updated'
            $table->json('payload_json');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['mission_id', 'id']);
            $table->index(['mission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_realtime_events');
    }
};
