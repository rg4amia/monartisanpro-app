<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidature par note vocale (≤ 20 s) : fichier sur disque privé,
 * transcription Gemini et statut de vérification anti-contournement.
 *
 * voice_status : pending (en cours de transcription) · approved (transcrite,
 * sans coordonnées : transmise au recruteur) · contact_detected (coordonnées
 * détectées : supprimée, jamais transmise) · failed (transcription
 * indisponible : retenue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table) {
            $table->string('voice_note_path')->nullable()->after('status');
            $table->unsignedTinyInteger('voice_note_duration')->nullable()->after('voice_note_path');
            $table->text('voice_transcription')->nullable()->after('voice_note_duration');
            $table->string('voice_status', 20)->nullable()->after('voice_transcription');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_applications', function (Blueprint $table) {
            $table->dropColumn(['voice_note_path', 'voice_note_duration', 'voice_transcription', 'voice_status']);
        });
    }
};
