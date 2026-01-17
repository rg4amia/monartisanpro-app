<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Infrastructure\Services\MobileMoney\MobileMoneyWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Controller for Mobile Money providers
 *
 * Handles webhook callbacks from Wave, Orange Money, and MTN Mobile Money
 * to update transaction statuses in real-time.
 *
 * Requirements: 15.4, 15.5
 */
class WebhookController extends Controller
{
 private MobileMoneyWebhookHandler $webhookHandler;

 public function __construct(MobileMoneyWebhookHandler $webhookHandler)
 {
  $this->webhookHandler = $webhookHandler;
 }

 /**
  * Handle Wave webhook callback
  *
  * @param Request $request
  * @return JsonResponse
  */
 public function handleWave(Request $request): JsonResponse
 {
  Log::info('Wave webhook received', [
   'headers' => $request->headers->all(),
   'payload_length' => strlen($request->getContent()),
  ]);

  $result = $this->webhookHandler->handleWaveWebhook($request);

  return response()->json($result, $result['status'] === 'success' ? 200 : 400);
 }

 /**
  * Handle Orange Money webhook callback
  *
  * @param Request $request
  * @return JsonResponse
  */
 public function handleOrangeMoney(Request $request): JsonResponse
 {
  Log::info('Orange Money webhook received', [
   'headers' => $request->headers->all(),
   'payload_length' => strlen($request->getContent()),
  ]);

  $result = $this->webhookHandler->handleOrangeMoneyWebhook($request);

  return response()->json($result, $result['status'] === 'success' ? 200 : 400);
 }

 /**
  * Handle MTN Mobile Money webhook callback
  *
  * @param Request $request
  * @return JsonResponse
  */
 public function handleMTN(Request $request): JsonResponse
 {
  Log::info('MTN Mobile Money webhook received', [
   'headers' => $request->headers->all(),
   'payload_length' => strlen($request->getContent()),
  ]);

  $result = $this->webhookHandler->handleMTNWebhook($request);

  return response()->json($result, $result['status'] === 'success' ? 200 : 400);
 }

 /**
  * Handle generic webhook callback (auto-detect provider)
  *
  * @param Request $request
  * @return JsonResponse
  */
 public function handleGeneric(Request $request): JsonResponse
 {
  Log::info('Generic webhook received', [
   'headers' => $request->headers->all(),
   'payload_length' => strlen($request->getContent()),
  ]);

  // Auto-detect provider based on headers or payload
  $provider = $this->detectProvider($request);

  switch ($provider) {
   case 'wave':
    return $this->handleWave($request);
   case 'orange':
    return $this->handleOrangeMoney($request);
   case 'mtn':
    return $this->handleMTN($request);
   default:
    Log::warning('Unknown webhook provider', [
     'headers' => $request->headers->all(),
    ]);
    return response()->json(['status' => 'error', 'message' => 'Unknown provider'], 400);
  }
 }

 /**
  * Detect mobile money provider from request
  *
  * @param Request $request
  * @return string|null
  */
 private function detectProvider(Request $request): ?string
 {
  // Check User-Agent header
  $userAgent = $request->header('User-Agent', '');
  if (str_contains(strtolower($userAgent), 'wave')) {
   return 'wave';
  }
  if (str_contains(strtolower($userAgent), 'orange')) {
   return 'orange';
  }
  if (str_contains(strtolower($userAgent), 'mtn')) {
   return 'mtn';
  }

  // Check custom headers
  if ($request->hasHeader('X-Wave-Signature')) {
   return 'wave';
  }
  if ($request->hasHeader('X-Orange-Signature')) {
   return 'orange';
  }
  if ($request->hasHeader('X-MTN-Signature')) {
   return 'mtn';
  }

  // Check payload structure
  $payload = $request->json();
  if ($payload && isset($payload['wave_transaction_id'])) {
   return 'wave';
  }
  if ($payload && isset($payload['orange_transaction_id'])) {
   return 'orange';
  }
  if ($payload && isset($payload['mtn_transaction_id'])) {
   return 'mtn';
  }

  return null;
 }
}
