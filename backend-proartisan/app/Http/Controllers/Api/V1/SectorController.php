<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;

class SectorController extends Controller
{
    public function index(): JsonResponse
    {
        $sectors = Sector::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $sectors->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => $s->name,
                'icon'  => $s->icon,
                'color' => $s->color,
            ]),
        ]);
    }

    public function trades(Sector $sector): JsonResponse
    {
        $trades = $sector->trades()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $trades->map(fn ($t) => [
                'id'       => $t->id,
                'name'     => $t->name,
                'sectorId' => $t->sector_id,
            ]),
        ]);
    }
}
