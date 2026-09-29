<?php

namespace App\Services;

use App\Exceptions\CircuitOpenException;
use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Orange SMS API (Paddock / GSMA OneAPI RESTful NetAPI)
 *
 * Spécification Swagger :
 *   - Endpoint : POST /smsmessaging/v1/outbound/{senderAddress}/requests
 *   - Protocole adresse : tel:+<MSISDN>
 *   - Authentification : Bearer token direct ou OAuth 2.0 (client_credentials)
 */
class OrangeSmsService
{
    public const CIRCUIT_PROVIDER = 'orange_sms';

    protected string $baseUrl;

    protected ?string $apiToken;

    protected ?string $clientId;

    protected ?string $clientSecret;

    protected string $senderAddress;

    protected string $senderName;

    public function __construct(
        private CircuitBreakerService $circuitBreaker = new CircuitBreakerService,
    ) {
        $this->baseUrl = rtrim(trim((string) config('services.orange_sms.base_url', 'https://api.orange.com')), '/');
        $this->apiToken = config('services.orange_sms.api_token') ? trim((string) config('services.orange_sms.api_token')) : null;
        $this->clientId = config('services.orange_sms.client_id') ? trim((string) config('services.orange_sms.client_id')) : null;
        $this->clientSecret = config('services.orange_sms.client_secret') ? trim((string) config('services.orange_sms.client_secret')) : null;
        $this->senderAddress = trim((string) config('services.orange_sms.sender_address', 'tel:+2250000'));
        $this->senderName = trim((string) config('services.orange_sms.sender_name', 'ProsArtisan'));
    }

    /**
     * Normalise un numéro de téléphone pour le protocole Orange 'tel:+'
     * Exemples :
     *   - 0141498409       → tel:+2250141498409
     *   - +2250141498409   → tel:+2250141498409
     *   - tel:+2250141498409 → tel:+2250141498409
     */
    public function formatAddress(string $phone, string $countryCode = '225'): string
    {
        $clean = preg_replace('/[\s\-\.\(\)]+/', '', $phone);

        if (str_starts_with($clean, 'tel:')) {
            $clean = substr($clean, 4);
        }

        $clean = ltrim($clean, '+');

        if (str_starts_with($clean, '00'.$countryCode)) {
            $clean = substr($clean, 2);
        }

        if (str_starts_with($clean, $countryCode) && strlen($clean) >= 12) {
            return 'tel:+'.$clean;
        }

        if (str_starts_with($clean, '0')) {
            return 'tel:+'.$countryCode.$clean;
        }

        return 'tel:+'.$countryCode.$clean;
    }

    /**
     * Normalise l'adresse de l'émetteur
     */
    public function formatSenderAddress(?string $senderAddress = null): string
    {
        $address = $senderAddress ?: $this->senderAddress;
        $address = trim($address);

        if (! str_starts_with($address, 'tel:')) {
            $address = 'tel:'.(str_starts_with($address, '+') ? $address : '+'.$address);
        }

        return $address;
    }

    /**
     * Obtient le token d'accès Bearer (soit configuré directement, soit via OAuth v3)
     *
     * @throws \Exception
     */
    public function getAccessToken(): string
    {
        if ($this->apiToken) {
            return $this->apiToken;
        }

        if (! $this->clientId || ! $this->clientSecret) {
            throw new \RuntimeException('Identifiants Orange SMS manquants (ORANGE_SMS_API_TOKEN ou ORANGE_SMS_CLIENT_ID / ORANGE_SMS_CLIENT_SECRET requis).');
        }

        $cacheKey = 'orange_sms_oauth_token_'.md5($this->clientId);

        return Cache::remember($cacheKey, 3300, function () {
            $tokenUrl = $this->baseUrl.'/oauth/v3/token';

            $client = Http::asForm()
                ->timeout(5)
                ->withHeaders([
                    'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ]);

            if (app()->environment('local', 'testing')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($tokenUrl, [
                'grant_type' => 'client_credentials',
            ]);

            if (! $response->successful()) {
                Log::error('Orange SMS OAuth Token Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Échec obtention token OAuth Orange SMS : HTTP '.$response->status());
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if (! $token) {
                throw new \RuntimeException("Réponse OAuth Orange SMS invalide : 'access_token' manquant.");
            }

            return (string) $token;
        });
    }

    /**
     * Construit le client HTTP authentifié
     */
    protected function httpClient(): PendingRequest
    {
        $token = $this->getAccessToken();

        $client = Http::timeout(5)
            ->connectTimeout(3)
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);

        if (app()->environment('local', 'testing')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Envoie un SMS à un destinataire via l'API Orange
     *
     * @param  string  $recipient  Numéro de téléphone
     * @param  string  $message  Contenu du SMS
     * @param  string|null  $senderName  Nom d'affichage expéditeur (optionnel)
     * @param  string|null  $clientCorrelator  Identifiant unique anti-rejeu (max 32 car.)
     * @return array Résultat standardisé
     */
    public function send(
        string $recipient,
        string $message,
        ?string $senderName = null,
        ?string $clientCorrelator = null
    ): array {
        // Fast-fail si le Circuit Breaker est ouvert
        try {
            $this->circuitBreaker->ensureAvailable(self::CIRCUIT_PROVIDER);
        } catch (CircuitOpenException $e) {
            Log::warning('Orange SMS CircuitBreaker Ouvert : envoi ignoré', [
                'recipient' => $recipient,
            ]);

            return [
                'status' => 'error',
                'provider' => 'orange',
                'message' => $e->getMessage(),
                'circuit_breaker' => 'open',
            ];
        }

        $formattedRecipient = $this->formatAddress($recipient);
        $senderAddress = $this->formatSenderAddress();
        $senderName = $senderName ?: $this->senderName;
        $clientCorrelator = $clientCorrelator ?: substr('pa_'.md5(uniqid($recipient, true)), 0, 32);

        $endpoint = $this->baseUrl.'/smsmessaging/v1/outbound/'.urlencode($senderAddress).'/requests';

        $payload = [
            'outboundSMSMessageRequest' => [
                'address' => $formattedRecipient,
                'senderAddress' => $senderAddress,
                'outboundSMSTextMessage' => [
                    'message' => $message,
                ],
                'senderName' => $senderName,
                'clientCorrelator' => $clientCorrelator,
            ],
        ];

        try {
            $response = $this->httpClient()->post($endpoint, $payload);

            if ($response->status() === 201 || $response->successful()) {
                $this->circuitBreaker->recordSuccess(self::CIRCUIT_PROVIDER);

                $responseData = $response->json();
                $resourceURL = $responseData['outboundSMSMessageRequest']['resourceURL'] ?? null;

                return [
                    'status' => 'success',
                    'provider' => 'orange',
                    'data' => [
                        'recipient' => $formattedRecipient,
                        'message' => $message,
                        'resource_url' => $resourceURL,
                        'raw' => $responseData,
                    ],
                ];
            }

            $this->circuitBreaker->recordFailure(self::CIRCUIT_PROVIDER);

            $errorBody = $response->json() ?? $response->body();
            $errorMessage = $this->extractErrorMessage($errorBody, $response->status());

            Log::error('Orange SMS API Error', [
                'status' => $response->status(),
                'recipient' => $formattedRecipient,
                'error' => $errorBody,
            ]);

            return [
                'status' => 'error',
                'provider' => 'orange',
                'message' => $errorMessage,
                'http_status' => $response->status(),
                'api_response' => $errorBody,
            ];
        } catch (\Exception $e) {
            $this->circuitBreaker->recordFailure(self::CIRCUIT_PROVIDER);

            Log::error('Orange SMS Exception', [
                'recipient' => $formattedRecipient,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'provider' => 'orange',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envoie un code OTP via l'API Orange
     */
    public function sendOtp(string $phone, string $code): array
    {
        $ttl = (int) config('prosartisan.otp.ttl', 5);
        $message = app(NotificationTemplateService::class)->render('auth.otp', ['code' => $code, 'minutes' => $ttl])['sms'];

        return $this->send($phone, $message, $this->senderName);
    }

    /**
     * Extrait un message d'erreur lisible depuis la réponse de l'API Orange
     */
    private function extractErrorMessage($errorBody, int $status): string
    {
        if (is_array($errorBody)) {
            $serviceError = $errorBody['requestError']['serviceException'] ?? null;
            if ($serviceError && isset($serviceError['text'])) {
                return $serviceError['messageId'].' : '.$serviceError['text'];
            }

            $policyError = $errorBody['requestError']['policyException'] ?? null;
            if ($policyError && isset($policyError['text'])) {
                return $policyError['messageId'].' : '.$policyError['text'];
            }

            if (isset($errorBody['message'])) {
                return (string) $errorBody['message'];
            }
        }

        return "Échec de l'envoi SMS via Orange (HTTP {$status})";
    }
}
