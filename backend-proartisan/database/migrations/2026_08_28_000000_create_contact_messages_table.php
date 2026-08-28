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
        if (!Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->string('nom', 150);
                $table->string('email', 255);
                $table->string('telephone', 30)->nullable();
                $table->string('sujet', 255);
                $table->text('message');
                $table->foreignId('artisan_id')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('statut', ['nouveau', 'en_cours', 'traite', 'archive'])->default('nouveau');
                $table->enum('priorite', ['basse', 'normale', 'urgente'])->default('normale');
                $table->text('notes_admin')->nullable();
                $table->text('reponse_envoyee')->nullable();
                $table->foreignId('traite_par_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('traite_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index('statut');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
