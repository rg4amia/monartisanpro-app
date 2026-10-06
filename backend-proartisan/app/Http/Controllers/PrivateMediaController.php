<?php

namespace App\Http\Controllers;

use App\Support\PrivateMedia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert un fichier privé d'un utilisateur (photo de course, reçu, photo
 * d'étape ou de litige, média de discussion, photo de mission).
 *
 * L'accès est porté par un lien signé à durée limitée, remis aux seuls acteurs
 * qui voient déjà la ressource à laquelle le fichier est attaché : la
 * signature est l'autorisation, comme pour les pièces KYC (Règle d'or 40).
 */
class PrivateMediaController extends Controller
{
    public function show(string $path): Response
    {
        if (! PrivateMedia::exists($path)) {
            abort(404);
        }

        return response()->file(PrivateMedia::absolutePath($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
