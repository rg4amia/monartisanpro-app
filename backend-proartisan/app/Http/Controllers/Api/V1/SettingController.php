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
                'app_access_disabled_message',
                'app_access_disabled_message_client',
                'app_access_disabled_message_artisan',
                'app_access_disabled_message_fournisseur',
                'app_access_disabled_message_livreur',
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
                'app_access_disabled_message_client' => $settings->get('app_access_disabled_message_client', 'L\'accès à l\'espace client est temporairement indisponible pour maintenance. Veuillez nous excuser pour la gêne occasionnée.'),
                'app_access_disabled_message_artisan' => $settings->get('app_access_disabled_message_artisan', 'L\'accès à l\'espace artisan est temporairement suspendu. Nos équipes interviennent rapidement. Merci de votre patience.'),
                'app_access_disabled_message_fournisseur' => $settings->get('app_access_disabled_message_fournisseur', 'L\'espace fournisseur est en cours de mise à jour technique. L\'accès sera rétabli sous peu.'),
                'app_access_disabled_message_livreur' => $settings->get('app_access_disabled_message_livreur', 'L\'espace de livraison est momentanément inaccessible. Merci de réessayer d\'ici quelques instants.'),
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
            'app_access_disabled_message_client' => 'nullable|string',
            'app_access_disabled_message_artisan' => 'nullable|string',
            'app_access_disabled_message_fournisseur' => 'nullable|string',
            'app_access_disabled_message_livreur' => 'nullable|string',
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
