<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private SmsService $smsService) {}

    /**
     * Send SMS to single or multiple recipients
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => 'required|string',
            'message' => 'required|string|max:1000',
            'sender_id' => 'nullable|string|max:11',
            'schedule_time' => 'nullable|date_format:Y-m-d H:i',
        ]);

        $result = $this->smsService->send(
            $validated['recipient'],
            $validated['message'],
            $validated['sender_id'] ?? config('services.sms.sender_id'),
            $validated['schedule_time'] ?? null
        );

        return response()->json($result);
    }

    /**
     * View SMS by UID
     */
    public function view(string $uid): JsonResponse
    {
        $result = $this->smsService->view($uid);
        return response()->json($result);
    }

    /**
     * View all messages
     */
    public function viewAll(): JsonResponse
    {
        $result = $this->smsService->viewAll();
        return response()->json($result);
    }
}
