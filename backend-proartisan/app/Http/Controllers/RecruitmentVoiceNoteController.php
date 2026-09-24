<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentApplication;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert la note vocale d'une candidature, stockée sur le disque privé.
 *
 * Même modèle que les pièces KYC (Règle d'or 40) : l'URL signée à courte
 * durée de vie, produite par RecruitmentApplication::voiceNoteUrl pour les
 * seuls acteurs qui voient déjà la candidature (recruteur ayant payé le
 * séquestre d'accès, administrateur, artisan lui-même), EST l'autorisation.
 */
class RecruitmentVoiceNoteController extends Controller
{
    public function show(RecruitmentApplication $application): Response
    {
        $path = $application->voice_note_path;

        if ($application->voice_status !== 'approved' || ! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=900, no-store',
        ]);
    }
}
