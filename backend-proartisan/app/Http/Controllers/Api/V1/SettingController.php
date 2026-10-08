<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(private AppAccessService $appAccess) {}

    /**
     * Get public app access settings
     */
    public function getAppAccess(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->appAccess->current(),
        ]);
    }

    /**
     * Update app access settings (Admin only)
     */
    public function updateAppAccess(Request $request): JsonResponse
    {
        $blockMode = ['required', Rule::in(AppAccessService::BLOCK_MODES)];

        $validated = $request->validate([
            'block_client' => $blockMode,
            'block_artisan' => $blockMode,
            'block_fournisseur' => $blockMode,
            'block_livreur' => $blockMode,
            'app_access_disabled_message' => 'nullable|string',
            'app_access_disabled_message_client' => 'nullable|string',
            'app_access_disabled_message_artisan' => 'nullable|string',
            'app_access_disabled_message_fournisseur' => 'nullable|string',
            'app_access_disabled_message_livreur' => 'nullable|string',
        ]);

        $this->appAccess->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Paramètres d\'accès mis à jour avec succès.',
            'data' => $validated,
        ]);
    }
}
