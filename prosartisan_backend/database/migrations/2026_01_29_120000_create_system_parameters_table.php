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
  Schema::create('system_parameters', function (Blueprint $table) {
   $table->id();
   $table->string('key')->unique();
   $table->text('value');
   $table->enum('type', ['string', 'integer', 'float', 'boolean', 'json', 'email', 'url', 'percentage']);
   $table->string('category')->default('general');
   $table->string('label');
   $table->text('description')->nullable();
   $table->boolean('is_public')->default(false);
   $table->boolean('is_editable')->default(true);
   $table->json('validation_rules')->nullable();
   $table->unsignedBigInteger('updated_by')->nullable();
   $table->timestamps();

   $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
   $table->index(['category', 'is_public']);
  });
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('system_parameters');
 }
};
