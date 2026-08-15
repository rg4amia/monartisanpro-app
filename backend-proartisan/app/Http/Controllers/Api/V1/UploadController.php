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
            'file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:10240', // 10MB max
        ]);

        $file = $request->file('file');

        // Stocke dans le dossier 'fileshare' du disque public
        $path = $file->store('fileshare', 'public');

        // Récupère l'URL publique (chemin absolu)
        $url = asset('storage/' . $path);

        return response()->json([
            'success' => true,
            'message' => 'Fichier uploadé avec succès dans le dossier fileshare.',
            'url'     => $url,
        ]);
    }
}
