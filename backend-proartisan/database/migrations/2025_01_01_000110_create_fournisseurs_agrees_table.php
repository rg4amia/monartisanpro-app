<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fournisseurs_agrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nom_boutique', 150);
            $table->enum('statut', ['en_attente', 'agree', 'suspendu'])->default('en_attente');
            $table->timestamp('approuve_at')->nullable();
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE fournisseurs_agrees ADD COLUMN position POINT NOT NULL AFTER nom_boutique');
            DB::statement('ALTER TABLE fournisseurs_agrees ADD SPATIAL INDEX idx_fournisseur_position (position)');
        } else {
            Schema::table('fournisseurs_agrees', function (Blueprint $table) {
                $table->string('position')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fournisseurs_agrees');
    }
};
