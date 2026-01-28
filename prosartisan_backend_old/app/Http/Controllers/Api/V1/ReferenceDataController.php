<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

class ReferenceDataController extends Controller
{
    /**
     * Get all sectors and trades
     */
    public function index()
    {
        $sectors = \App\Models\Sector::with('trades')->get();

        return response()->json(['data' => $sectors]);
    }
}
