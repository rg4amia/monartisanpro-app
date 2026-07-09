<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jury_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('litige_id')->constrained('litiges')->onDelete('cascade');
            $table->foreignId('jure_id')->constrained('users')->onDelete('cascade');
            $table->enum('verdict', ['CONFORME', 'NON_CONFORME'])->nullable();
            $table->timestamp('voted_at')->nullable();
            $table->bigInteger('compensation')->default(0);
            $table->timestamps();

            $table->unique(['litige_id', 'jure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jury_reviews');
    }
};
