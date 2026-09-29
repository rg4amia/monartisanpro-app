<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ArtisanAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Disponibilité de l'artisan connecté pour l'annuaire du site vitrine
 * (Chantier 15). L'artisan ne lit et ne déclare que la sienne ; toute
 * déclaration attend la validation d'un administrateur.
 */
class ArtisanAvailabilityController extends Controller
{
    public function __construct(private ArtisanAvailabilityService $availabilities) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'artisan', 403, 'Réservé aux artisans.');

        return response()->json([
            'success' => true,
            'data' => $this->availabilities->overviewFor($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'artisan', 403, 'Réservé aux artisans.');

        $validated = $request->validate([
            'status' => 'required|string',
            'until_date' => 'nullable|date',
            'schedule' => 'nullable|array',
            'schedule.*.day' => 'required|integer',
            'schedule.*.start' => 'required|string',
            'schedule.*.end' => 'required|string',
            'night_work' => 'nullable|boolean',
        ]);

        $this->availabilities->submit($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité envoyée : elle sera publiée dans l\'annuaire après validation.',
            'data' => $this->availabilities->overviewFor($request->user()),
        ], 201);
    }
}
