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

    /**
     * Diagnostic de connectivité réseau vers SMS Pro Africa (demandé par le support SMS Pro)
     */
    public function smsDiagnostics(): JsonResponse
    {
        $serverIp = @file_get_contents('https://api.ipify.org');

        // Test IPv4
        $ch4 = curl_init('https://app.smspro.africa/api/v3/sms/send');
        curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch4, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch4, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch4, CURLOPT_CONNECTTIMEOUT, 6);
        $resV4 = curl_exec($ch4);
        $errV4 = curl_error($ch4);
        $codeV4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
        curl_close($ch4);

        // Test IPv6
        $ch6 = curl_init('https://app.smspro.africa/api/v3/sms/send');
        curl_setopt($ch6, CURLOPT_RETURNTRANSFER, true);
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V6')) {
            curl_setopt($ch6, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V6);
        }
        curl_setopt($ch6, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch6, CURLOPT_CONNECTTIMEOUT, 6);
        $resV6 = curl_exec($ch6);
        $errV6 = curl_error($ch6);
        $codeV6 = curl_getinfo($ch6, CURLINFO_HTTP_CODE);
        curl_close($ch6);

        return response()->json([
            'server_outbound_ip' => $serverIp ?: 'indéterminée',
            'ipv4_test' => [
                'http_code' => $codeV4,
                'error' => $errV4 ?: null,
                'success' => empty($errV4) && $codeV4 > 0,
            ],
            'ipv6_test' => [
                'http_code' => $codeV6,
                'error' => $errV6 ?: null,
                'success' => empty($errV6) && $codeV6 > 0,
            ],
            'active_provider' => app(\App\Services\SmsService::class)->getProvider(),
            'token_length' => strlen(config('services.sms.api_token') ?? ''),
            'otp_type' => config('services.sms.otp_type'),
        ]);
    }
}
