<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_vault', function (Blueprint $table) {
            $table->id();
            $table->foreignId('litige_id')->constrained('litiges')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('file_url');
            $table->string('sha256_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_vault');
    }
};
