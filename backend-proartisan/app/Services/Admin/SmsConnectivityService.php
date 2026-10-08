<?php

namespace App\Services\Admin;

use App\Services\SmsService;
use Illuminate\Support\Facades\Http;

/**
 * Diagnostic de connectivité du serveur vers SMS Pro Africa.
 *
 * Réservé au backoffice : il révèle l'adresse IP de sortie du serveur et
 * déclenche des appels sortants à chaque requête.
 */
class SmsConnectivityService
{
    private const TIMEOUT_SECONDS = 6;

    public function __construct(private SmsService $sms) {}

    /**
     * @return array<string, mixed>
     */
    public function diagnose(): array
    {
        $target = rtrim((string) config('services.sms.base_url'), '/').'/sms/send';

        return [
            'server_outbound_ip' => $this->outboundIp() ?? 'indéterminée',
            'ipv4_test' => $this->probe($target, 'v4'),
            'ipv6_test' => $this->probe($target, 'v6'),
            'active_provider' => $this->sms->getProvider(),
            // Présence seulement : ni le jeton, ni sa longueur.
            'token_configured' => trim((string) config('services.sms.api_token')) !== '',
            'otp_type' => config('services.sms.otp_type'),
        ];
    }

    /**
     * Une réponse HTTP, quelle qu'elle soit (401 sans jeton), prouve que le
     * serveur joint SMS Pro ; seule l'absence de réponse est un échec.
     *
     * @return array{http_code: int, error: string|null, success: bool}
     */
    private function probe(string $url, string $ipVersion): array
    {
        try {
            $response = Http::withOptions(['force_ip_resolve' => $ipVersion])
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->get($url);

            return ['http_code' => $response->status(), 'error' => null, 'success' => true];
        } catch (\Throwable $e) {
            return ['http_code' => 0, 'error' => $e->getMessage(), 'success' => false];
        }
    }

    private function outboundIp(): ?string
    {
        try {
            $ip = trim(Http::timeout(self::TIMEOUT_SECONDS)->get('https://api.ipify.org')->body());

            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
