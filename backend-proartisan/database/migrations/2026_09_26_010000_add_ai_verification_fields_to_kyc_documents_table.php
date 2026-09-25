<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_documents')) {
            Schema::table('kyc_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('kyc_documents', 'ocr_data')) {
                    $table->json('ocr_data')->nullable();
                }
                if (! Schema::hasColumn('kyc_documents', 'ai_confidence_score')) {
                    $table->unsignedTinyInteger('ai_confidence_score')->nullable();
                }
                if (! Schema::hasColumn('kyc_documents', 'ai_analysis')) {
                    $table->json('ai_analysis')->nullable();
                }
                if (! Schema::hasColumn('kyc_documents', 'auto_verified')) {
                    $table->boolean('auto_verified')->default(false);
                }
                if (! Schema::hasColumn('kyc_documents', 'face_matched')) {
                    $table->boolean('face_matched')->default(false);
                }
                // Numéro de pièce normalisé, extrait par OCR : une même pièce
                // ne peut valider qu'un seul compte (recherche de doublons).
                if (! Schema::hasColumn('kyc_documents', 'ocr_document_number')) {
                    $table->string('ocr_document_number', 64)->nullable();
                }
            });

            // Index posé dans un second ALTER, une fois la colonne créée (Règle d'or 55).
            if (Schema::hasColumn('kyc_documents', 'ocr_document_number')
                && ! Schema::hasIndex('kyc_documents', 'kyc_documents_ocr_document_number_index')) {
                Schema::table('kyc_documents', function (Blueprint $table) {
                    $table->index('ocr_document_number');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kyc_documents')) {
            if (Schema::hasIndex('kyc_documents', 'kyc_documents_ocr_document_number_index')) {
                Schema::table('kyc_documents', function (Blueprint $table) {
                    $table->dropIndex('kyc_documents_ocr_document_number_index');
                });
            }

            Schema::table('kyc_documents', function (Blueprint $table) {
                $columns = ['ocr_data', 'ocr_document_number', 'ai_confidence_score', 'ai_analysis', 'auto_verified', 'face_matched'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('kyc_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
