<?php

namespace App\Jobs;

use App\Services\RecruitmentService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Transcription de la note vocale d'une candidature, exécutée juste après
 * l'envoi de la réponse HTTP (dispatchAfterResponse) : l'artisan n'attend pas
 * Gemini, et aucun worker de file d'attente n'est requis.
 */
class TranscribeRecruitmentVoiceNote
{
    use Dispatchable;

    public function __construct(public int $applicationId) {}

    public function handle(RecruitmentService $recruitment): void
    {
        $recruitment->transcribeVoiceNote($this->applicationId);
    }
}
