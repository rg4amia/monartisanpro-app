<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\Admin\MissionHistoryAdminService;
use App\Services\MissionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Backoffice — historique des états des missions : consultation par mission,
 * contrôle des historiques incomplets et reconstitution (Règle d'or 89).
 */
class AdminMissionHistoryController extends Controller
{
    public function __construct(
        private MissionHistoryService $history,
        private MissionHistoryAdminService $admin,
    ) {}

    public function show(Mission $mission): JsonResponse
    {
        return response()->json($this->history->timeline($mission));
    }

    public function check(): JsonResponse
    {
        return response()->json($this->admin->check());
    }

    public function rebuild(Request $request): RedirectResponse
    {
        $report = $this->admin->rebuild($request->user());

        return back()->with('success', $report['lines'] === 0
            ? "L'historique des missions est déjà complet."
            : "Historique reconstitué : {$report['lines']} ligne(s) ajoutée(s) sur {$report['incomplete']} mission(s).");
    }
}
