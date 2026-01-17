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
}
