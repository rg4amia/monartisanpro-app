<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf,mp4,mov,avi,mkv,3gp,m4v|max:25600', // 25MB max
        ]);

        $file = $request->file('file');

        // Analyse par l'IA Gemini pour interdire les coordonnées / adresses / localisation
        $gemini = app(\App\Services\GeminiService::class);
        $analysis = $gemini->analyzeMediaForSensitiveData($file);

        if ($analysis['contains_sensitive_data']) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier rejeté : ' . $analysis['details'],
            ], 422);
        }

        // Stocke dans le dossier 'fileshare' du disque public
        $path = $file->store('fileshare', 'public');

        // Récupère l'URL publique (chemin absolu garanti en HTTPS)
        $baseUrl = config('app.url') ?: 'https://prosartisan.net';
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1') || app()->environment('production')) {
            $baseUrl = 'https://prosartisan.net';
        }
        $baseUrl = rtrim(str_replace('http://', 'https://', $baseUrl), '/');
        $url = $baseUrl . '/storage/' . $path;

        return response()->json([
            'success' => true,
            'message' => 'Fichier uploadé avec succès dans le dossier fileshare.',
            'url'     => $url,
        ]);
    }
}
