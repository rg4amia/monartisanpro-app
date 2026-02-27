<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('artisan_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->json('photos_json')->nullable();
            $table->string('gemini_category')->nullable();
            $table->enum('gemini_urgency', ['faible', 'moyen', 'urgent'])->nullable();
            $table->bigInteger('gemini_estimation_min')->nullable();
            $table->bigInteger('gemini_estimation_max')->nullable();
            $table->enum('status', ['en_attente', 'financee', 'en_cours', 'terminee', 'litige'])->default('en_attente');
            $table->bigInteger('montant_total')->default(0);
            $table->bigInteger('montant_materiaux')->default(0);
            $table->bigInteger('montant_mo')->default(0);
            $table->decimal('ratio_materiaux', 5, 4)->nullable();
            $table->boolean('referent_required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
