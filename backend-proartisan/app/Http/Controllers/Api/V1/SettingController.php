<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Get public app access settings
     */
    public function getAppAccess(): JsonResponse
    {
        $settings = DB::table('settings')
            ->whereIn('key', [
                'block_client',
                'block_artisan',
                'block_fournisseur',
                'block_livreur',
                'app_access_disabled_message'
            ])
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => [
                'block_client' => $settings->get('block_client', 'none'),
                'block_artisan' => $settings->get('block_artisan', 'none'),
                'block_fournisseur' => $settings->get('block_fournisseur', 'none'),
                'block_livreur' => $settings->get('block_livreur', 'none'),
                'app_access_disabled_message' => $settings->get('app_access_disabled_message', 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.'),
            ],
        ]);
    }

    /**
     * Update app access settings (Admin only)
     */
    public function updateAppAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'block_client' => 'required|in:none,new,old,all',
            'block_artisan' => 'required|in:none,new,old,all',
            'block_fournisseur' => 'required|in:none,new,old,all',
            'block_livreur' => 'required|in:none,new,old,all',
            'app_access_disabled_message' => 'nullable|string',
        ]);

        foreach ($validated as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value ?? '',
                    'type' => 'string',
                    'group' => 'app_access',
                    'updated_at' => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Paramètres d\'accès mis à jour avec succès.',
            'data' => $validated,
        ]);
    }
}
