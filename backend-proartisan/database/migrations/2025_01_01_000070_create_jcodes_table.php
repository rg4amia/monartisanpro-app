<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained();
            $table->foreignId('artisan_id')->constrained('users');
            $table->foreignId('fournisseur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 7)->unique();
            $table->text('qr_url')->nullable();
            $table->string('ussd_code', 20)->nullable();
            $table->bigInteger('montant');
            $table->enum('statut', ['actif', 'utilise', 'expire'])->default('actif');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // position_scan nullable → pas d'index spatial
        DB::statement('ALTER TABLE jcodes ADD COLUMN position_scan POINT SRID 4326 NULL AFTER statut');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jcodes DROP COLUMN IF EXISTS position_scan');
        Schema::dropIfExists('jcodes');
    }
};
