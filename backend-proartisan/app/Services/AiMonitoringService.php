<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AiMonitoringService
{
    /**
     * Estimates cost in USD for a given model and token usage.
     */
    public static function estimateCost(string $modelName, int $promptTokens, int $completionTokens): float
    {
        // Pricing per million tokens (approximate prices for Gemini free-tier / pay-as-you-go standard tier)
        $pricing = [
            'gemini-3.6-flash' => [
                'input' => 0.075 / 1000000,
                'output' => 0.30 / 1000000
            ],
            'gemini-3.5-flash' => [
                'input' => 0.075 / 1000000,
                'output' => 0.30 / 1000000
            ],
            'gemini-3.1-flash-lite' => [
                'input' => 0.03 / 1000000,
                'output' => 0.12 / 1000000
            ],
            'gemini-2.0-flash' => [
                'input' => 0.075 / 1000000,
                'output' => 0.30 / 1000000
            ],
            'text-embedding-004' => [
                'input' => 0.025 / 1000000,
                'output' => 0.0
            ]
        ];

        $modelKey = str_replace('models/', '', $modelName);
        $rate = $pricing[$modelKey] ?? [
            'input' => 0.075 / 1000000,
            'output' => 0.30 / 1000000
        ];

        return ($promptTokens * $rate['input']) + ($completionTokens * $rate['output']);
    }

    /**
     * Logs an AI request.
     */
    public static function log(
        string $modelName,
        string $actionType,
        int $promptTokens,
        int $completionTokens,
        float $responseTimeMs,
        int $statusCode,
        ?string $errorMessage = null,
        ?int $userId = null
    ): void {
        try {
            $totalTokens = $promptTokens + $completionTokens;
            $estimatedCost = self::estimateCost($modelName, $promptTokens, $completionTokens);
            $userId = $userId ?? Auth::id();

            DB::table('ai_usage_logs')->insert([
                'user_id' => $userId,
                'model_name' => $modelName,
                'action_type' => $actionType,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'response_time_ms' => $responseTimeMs,
                'status_code' => $statusCode,
                'error_message' => $errorMessage ? substr($errorMessage, 0, 1000) : null,
                'estimated_cost_usd' => $estimatedCost,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            // Prevent monitoring errors from crashing the main application flow
            \Illuminate\Support\Facades\Log::error('Failed to write AI usage log: ' . $e->getMessage());
        }
    }

    /**
     * Checks if a user is within their daily interaction limit.
     */
    public static function checkUserLimit(?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) {
            return true; // Don't block unauthenticated / guest sessions unless required
        }

        // Check if AI is completely disabled
        $aiEnabled = DB::table('ai_settings')->where('key', 'ai_enabled')->value('value');
        if ($aiEnabled !== null && (int)$aiEnabled === 0) {
            return false;
        }

        // Get configured daily limit per user
        $dailyLimit = (int) DB::table('ai_settings')->where('key', 'daily_user_limit')->value('value');
        if ($dailyLimit <= 0) {
            return true; // Unlimited if set to <= 0
        }

        // Count successful/attempted interactions in the last 24h
        $count = DB::table('ai_usage_logs')
            ->where('user_id', $userId)
            ->where('action_type', 'chat') // Only rate limit chat interactions
            ->where('status_code', 200)   // Count only successful responses
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count < $dailyLimit;
    }
}
