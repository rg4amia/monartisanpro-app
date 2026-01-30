<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Trade;

class ReferenceDataController extends Controller
{
    /**
     * Get all sectors and trades
     */
    public function index()
    {
        $sectors = Sector::with('trades')->get();

        return response()->json(['data' => $sectors]);
    }

    /**
     * Get all sectors only
     */
    public function sectors()
    {
        $sectors = Sector::orderBy('name')->get();

        return response()->json(['data' => $sectors]);
    }

    /**
     * Get trades for a specific sector
     */
    public function tradesBySector($sectorId)
    {
        $trades = Trade::where('sector_id', $sectorId)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $trades]);
    }

    /**
     * Get all trades
     */
    public function trades()
    {
        $trades = Trade::with('sector')->orderBy('name')->get();

        return response()->json(['data' => $trades]);
    }
}
