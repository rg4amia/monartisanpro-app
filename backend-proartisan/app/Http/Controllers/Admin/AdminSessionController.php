<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminIdleSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session du backoffice : maintien et fermeture pour inactivité (Chantier 18).
 */
class AdminSessionController extends Controller
{
    public function __construct(private AdminIdleSessionService $idle) {}

    /**
     * Signale une activité sans requête (longue saisie dans un formulaire).
     * L'horodatage est mis à jour par le middleware `EnforceAdminIdleTimeout`.
     */
    public function keepAlive(): JsonResponse
    {
        return response()->json(['success' => true, 'timeout_seconds' => $this->idle->timeoutSeconds()]);
    }

    /**
     * Fermeture demandée par l'écran, le délai d'inactivité étant atteint.
     */
    public function expire(Request $request): JsonResponse
    {
        if ($this->idle->concerns($request)) {
            $this->idle->close($request, 'ecran');
        }

        return response()->json(['success' => true, 'redirect' => route('admin.login')]);
    }
}
