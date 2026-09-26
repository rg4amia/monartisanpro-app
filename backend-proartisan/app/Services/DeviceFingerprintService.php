<?php

namespace App\Services;

use App\Models\DeviceFingerprint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DeviceFingerprintService
{
    /**
     * Extrait les informations matérielles et réseau depuis la requête HTTP.
     */
    public function extractFromRequest(Request $request): array
    {
        $fingerprint = $request->header('X-Device-Fingerprint')
            ?? $request->input('device_fingerprint')
            ?? $request->header('X-App-Installation-Id')
            ?? null;

        $model = $request->header('X-Device-Model')
            ?? $request->input('device_model')
            ?? null;

        $installationId = $request->header('X-App-Installation-Id')
            ?? $request->input('app_installation_id')
            ?? null;

        $ip = $request->ip();
        $subnet = $this->calculateSubnet($ip);
        $userAgent = $request->userAgent();

        // Si aucun fingerprint explicite n'est envoyé, on le calcule à partir des attributs stables
        if (! $fingerprint && $userAgent && $ip) {
            $fingerprint = hash('sha256', $userAgent.'|'.$ip.'|'.($model ?? 'unknown'));
        }

        return [
            'device_fingerprint' => $fingerprint,
            'device_model' => $model,
            'app_installation_id' => $installationId,
            'ip_address' => $ip,
            'ip_subnet' => $subnet,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * Enregistre ou met à jour l'empreinte de l'utilisateur.
     */
    public function record(User $user, Request $request): ?DeviceFingerprint
    {
        $info = $this->extractFromRequest($request);

        if (empty($info['device_fingerprint'])) {
            return null;
        }

        $record = DeviceFingerprint::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $info['device_fingerprint'],
            ],
            [
                'device_model' => $info['device_model'],
                'app_installation_id' => $info['app_installation_id'],
                'ip_address' => $info['ip_address'],
                'ip_subnet' => $info['ip_subnet'],
                'user_agent' => $info['user_agent'],
                'last_seen_at' => now(),
            ]
        );

        if ($user->device_fingerprint !== $info['device_fingerprint']) {
            $user->update(['device_fingerprint' => $info['device_fingerprint']]);
        }

        return $record;
    }

    /**
     * Calcule le subnet IPv4 /24 ou IPv6 /64.
     */
    public function calculateSubnet(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 4) {
                return "{$parts[0]}:{$parts[1]}:{$parts[2]}:{$parts[3]}::/64";
            }
        }

        return $ip;
    }

    /**
     * Trouve les utilisateurs partageant la même empreinte d'appareil.
     */
    public function getUsersSharingDevice(string $deviceFingerprint, ?int $excludeUserId = null): Collection
    {
        $query = DeviceFingerprint::where('device_fingerprint', $deviceFingerprint);

        if ($excludeUserId) {
            $query->where('user_id', '!=', $excludeUserId);
        }

        return $query->with('user')->get()->pluck('user')->unique('id')->values();
    }
}
