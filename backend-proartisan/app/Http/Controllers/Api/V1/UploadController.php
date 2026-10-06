<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GeminiService;
use App\Support\PrivateMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            // Photos et vidéos seulement, jugées sur le contenu. Un PDF n'est pas lu
            // par l'analyse des données sensibles : il la traversait sans contrôle.
            'file' => 'required|file|mimes:jpeg,jpg,png,webp,mp4,mov,avi,mkv,3gp,m4v|max:25600', // 25MB max
            'usage' => 'nullable|string|in:catalogue,mission',
        ]);

        $file = $request->file('file');

        // Analyse par l'IA Gemini pour interdire les coordonnées / adresses / localisation
        $gemini = app(GeminiService::class);
        $analysis = $gemini->analyzeMediaForSensitiveData($file);

        if ($analysis['contains_sensitive_data']) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier rejeté : '.$analysis['details'],
            ], 422);
        }

        // Décision du 06/10/2026 : seules les images de catalogue sont
        // publiques. Tout autre fichier (photos et vidéos d'une demande de
        // mission) part sur le disque privé et se lit par lien signé.
        if ($this->isCatalogueImage($request)) {
            $path = $file->store('fileshare', 'public');

            // Adresse publique (garantie en HTTPS en production)
            $baseUrl = config('app.url') ?: 'https://prosartisan.net';
            if (app()->environment('production')) {
                $baseUrl = 'https://prosartisan.net';
            }
            $baseUrl = rtrim(str_replace('http://', 'https://', $baseUrl), '/');
            $url = $baseUrl.'/storage/'.$path;
        } else {
            $url = PrivateMedia::storeAndSign($file, 'fileshare');
        }

        return response()->json([
            'success' => true,
            'message' => 'Fichier envoyé avec succès.',
            'url' => $url,
        ]);
    }

    /**
     * Une image de catalogue : l'usage est annoncé (`usage=catalogue`), ou
     * l'expéditeur est un fournisseur — les versions installées de
     * l'application n'annoncent pas l'usage, et un fournisseur n'envoie par
     * cette route que les images de ses produits. Une vidéo n'est jamais une
     * image de catalogue.
     */
    private function isCatalogueImage(Request $request): bool
    {
        if (! str_starts_with((string) $request->file('file')->getMimeType(), 'image/')) {
            return false;
        }

        $usage = $request->input('usage');
        if ($usage !== null) {
            return $usage === 'catalogue' && $request->user()->role === 'fournisseur';
        }

        return $request->user()->role === 'fournisseur';
    }
}
