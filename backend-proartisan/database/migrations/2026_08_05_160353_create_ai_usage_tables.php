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
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('model_name');      // ex: gemini-3.6-flash, text-embedding-004, qdrant
            $table->string('action_type');     // ex: chat, embedding, search
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->float('response_time_ms');  // Temps de réponse en millisecondes
            $table->integer('status_code');    // 200, 503, 429, etc.
            $table->text('error_message')->nullable();
            $table->decimal('estimated_cost_usd', 8, 6)->default(0.000000);
            $table->timestamps();
        });

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // ex: daily_user_limit
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert default limits
        DB::table('ai_settings')->insert([
            [
                'key' => 'daily_user_limit',
                'value' => '20',
                'description' => 'Nombre maximum d\'interactions IA par jour et par utilisateur.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ai_enabled',
                'value' => '1',
                'description' => 'Activer ou désactiver complètement les fonctionnalités d\'IA (1 pour activer, 0 pour désactiver).',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('ai_usage_logs');
    }
};
