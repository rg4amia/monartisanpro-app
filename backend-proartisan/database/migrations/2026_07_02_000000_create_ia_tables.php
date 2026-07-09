<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Professions
        Schema::create('professions', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('profession_id', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('profession_id')->references('id')->on('professions')->onDelete('cascade');
        });

        // 3. Contexts (Reference Normes/Dosages)
        Schema::create('contexts', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('category_id', 50);
            $table->text('tags');
            $table->string('title');
            $table->string('source');
            $table->text('execution');
            $table->text('pitch');
            $table->json('dosages')->nullable();
            $table->json('materials')->nullable();
            $table->string('price')->nullable();
            $table->json('tools')->nullable();
            $table->json('safety')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // 4. Staging Items (Attente validation)
        Schema::create('staging_items', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('raw_pdf_source');
            $table->text('original_extracted_text');
            $table->json('generated_json');
            $table->string('status', 50)->default('PENDING');
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        // 5. Production Items (Validés)
        Schema::create('production_items', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->json('generated_json');
            $table->text('tags');
            $table->timestamps();
        });

        // 6. Import History
        Schema::create('import_history', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('filename');
            $table->integer('file_size');
            $table->string('imported_at', 100);
            $table->string('status', 50);
            $table->boolean('vlm_extracted')->default(false);
            $table->boolean('llm_downscaled')->default(false);
            $table->timestamps();
        });

        // 7. Attachments
        Schema::create('attachments', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('original_filename');
            $table->string('extension', 20);
            $table->string('file_link', 500);
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });

        // 8. Administrative Users specific to IA microservice (renamed to ia_users to avoid Laravel users table conflict)
        Schema::create('ia_users', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('password_hash', 255);
            $table->string('reset_token', 255)->nullable();
            $table->timestamp('reset_token_expiry')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_users');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('import_history');
        Schema::dropIfExists('production_items');
        Schema::dropIfExists('staging_items');
        Schema::dropIfExists('contexts');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('professions');
    }
};
