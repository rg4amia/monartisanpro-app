<?php

namespace App\Http\Controllers;

use App\Models\KycDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert une pièce KYC stockée sur le disque privé.
 *
 * L'accès est porté par une URL signée à durée limitée, produite par
 * KycDocument::fileUrl pour les acteurs qui ont déjà le droit de voir le
 * dossier (le titulaire via /kyc/status, l'administrateur via le backoffice).
 * Le fichier n'est jamais exposé par une URL publique permanente.
 */
class KycDocumentController extends Controller
{
    public function show(KycDocument $document): Response
    {
        if ($document->isLegacy()) {
            // Pièce d'avant la migration : encore sur le disque public.
            abort(404);
        }

        $path = $document->storagePath();

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            // Empêche l'affichage inline d'un contenu deviné : ces fichiers
            // sont validés à l'upload (JPEG/PNG uniquement).
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=900, no-store',
        ]);
    }
}
