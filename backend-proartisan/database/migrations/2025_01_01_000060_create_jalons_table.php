<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jalons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre');
            $table->string('description');
            $table->bigInteger('montant');
            $table->enum('statut', ['en_attente', 'soumis', 'valide', 'paye'])->default('en_attente');
            $table->string('otp_code', 4)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->json('photos_json')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->timestamp('paye_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jalons');
    }
};
