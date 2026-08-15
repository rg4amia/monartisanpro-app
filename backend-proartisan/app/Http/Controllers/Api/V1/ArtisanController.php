<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtisanResource;
use App\Models\User;
use App\Services\GeoService;
use App\Services\ScoreService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtisanController extends Controller
{
    public function __construct(
        private GeoService $geoService,
        private ScoreService $scoreService,
        private PdfService $pdfService,
    ) {}

    /**
     * Retourne les artisans proches avec position FLOUTÉE.
     * RÈGLE CRITIQUE : jamais de position exacte au client.
     */
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:150000'],
            'sector' => ['nullable'],
            'trade' => ['nullable'],
            'intervention_nuit' => ['nullable', 'boolean'],
        ], [
            'lat.required' => 'La latitude est obligatoire.',
            'lng.required' => 'La longitude est obligatoire.',
        ]);

        $radius   = $data['radius'] ?? config('prosartisan.gps.nearby_artisan_radius', 2000);
        $isFallback = false;

        $artisans = $this->geoService->nearbyArtisans(
            (float) $data['lat'],
            (float) $data['lng'],
            (int) $radius,
            isset($data['sector']) ? (string) $data['sector'] : null,
            isset($data['trade']) ? (string) $data['trade'] : null,
            (bool) ($data['intervention_nuit'] ?? false),
        );

        if ($artisans->isEmpty()) {
            // Aucun artisan trouvé dans le rayon initial (ex: 2km).
            // Élargissement de la recherche à 150 km pour proposer des artisans d'autres zones/communes (ex: plombier distant).
            $isFallback = true;
            $radius = 150000;
            $artisans = $this->geoService->nearbyArtisans(
                (float) $data['lat'],
                (float) $data['lng'],
                (int) $radius,
                isset($data['sector']) ? (string) $data['sector'] : null,
                isset($data['trade']) ? (string) $data['trade'] : null,
                (bool) ($data['intervention_nuit'] ?? false),
            );
        }

        $blurRadius = config('prosartisan.gps.artisan_blur_radius', 50);

        $result = $artisans->map(function ($row) use ($blurRadius) {
            $user = User::with(['artisanProfile.sector', 'artisanProfile.trade', 'commune'])
                ->find($row->id);

            if (! $user) {
                return null;
            }

            // Flouter la position GPS avant envoi
            $blurred = $this->geoService->blurPosition(
                (float) $row->lat,
                (float) $row->lng,
                $blurRadius
            );

            return new ArtisanResource($user, (float) $row->distance_metres, $blurred);
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data'    => $result,
            'meta'    => [
                'total'       => $result->count(),
                'radius'      => $radius,
                'is_fallback' => $isFallback,
                'intervention_nuit' => (bool) ($data['intervention_nuit'] ?? false),
                'center'      => ['lat' => (float) $data['lat'], 'lng' => (float) $data['lng']],
            ],
        ]);
    }

    /**
     * Profil complet d'un artisan (pour le client).
     */
    public function show(User $user): JsonResponse
    {
        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        $user->load('artisanProfile.sector', 'artisanProfile.trade', 'evaluationsRecues', 'commune');
        $coords = $user->getPositionCoords();

        // Flouter la position
        $blurred = $coords
            ? $this->geoService->blurPosition($coords['lat'], $coords['lng'])
            : null;

        return response()->json([
            'success' => true,
            'data'    => new ArtisanResource($user, null, $blurred),
        ]);
    }

    /**
     * Score ProsArtisan d'un artisan.
     */
    public function score(User $user): JsonResponse
    {
        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        $scoreDetail = $this->scoreService->getScoreDetail($user);

        return response()->json([
            'success' => true,
            'data'    => $scoreDetail,
        ]);
    }

    /**
     * Télécharger le rapport de solvabilité PDF.
     */
    public function downloadReport(User $user)
    {
        if ($user->role !== 'artisan') {
            abort(404);
        }

        $path = $this->pdfService->generateSolvabilityReport($user);

        return response()->download($path);
    }
}
