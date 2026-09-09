<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InterventionType;
use Illuminate\Http\JsonResponse;

class InterventionTypeController extends Controller
{
    /**
     * Liste des types d'intervention proposés au client à la demande de
     * mission (ex: simple déplacement/diagnostic vs. intervention complète).
     */
    public function index(): JsonResponse
    {
        $types = InterventionType::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $types->map(fn (InterventionType $type) => [
                'id'            => $type->id,
                'name'          => $type->name,
                'requiresLabor' => $type->requires_labor,
            ]),
        ]);
    }
}
