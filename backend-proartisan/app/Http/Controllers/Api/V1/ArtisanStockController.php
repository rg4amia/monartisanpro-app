<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArtisanStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtisanStockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $stock = ArtisanStock::where('artisan_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'data'    => $stock,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string',
            'quantity'    => 'required|integer|min:1',
            'unit_cost'   => 'required|integer|min:0',
            'condition'   => 'required|in:neuf,occasion',
        ]);

        $stock = ArtisanStock::create(array_merge($data, ['artisan_id' => $request->user()->id]));

        return response()->json([
            'success' => true,
            'data'    => $stock,
        ], 201);
    }

    public function update(Request $request, ArtisanStock $artisanStock): JsonResponse
    {
        if ($artisanStock->artisan_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $data = $request->validate([
            'description' => 'sometimes|string',
            'quantity'    => 'sometimes|integer|min:0',
            'unit_cost'   => 'sometimes|integer|min:0',
            'condition'   => 'sometimes|in:neuf,occasion',
        ]);

        $artisanStock->update($data);

        return response()->json([
            'success' => true,
            'data'    => $artisanStock,
        ]);
    }

    public function destroy(Request $request, ArtisanStock $artisanStock): JsonResponse
    {
        if ($artisanStock->artisan_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $artisanStock->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock supprimé.',
        ]);
    }
}
