<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert la photo de profil d'un utilisateur, stockée sur le disque privé.
 *
 * Même principe que KycDocumentController : l'accès passe par une URL signée
 * à durée limitée (cf. User::photoUrl), jamais par une URL publique permanente.
 */
class UserPhotoController extends Controller
{
    public function show(User $user): Response
    {
        $path = $user->photo_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=900, no-store',
        ]);
    }
}
