<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                'output' => 0.30 / 1000000,
            ],
            'gemini-3.5-flash' => [
                'input' => 0.075 / 1000000,
                'output' => 0.30 / 1000000,
            ],
            'gemini-3.1-flash-lite' => [
                'input' => 0.03 / 1000000,
                'output' => 0.12 / 1000000,
            ],
            'gemini-1.5-flash' => [
                'input' => 0.075 / 1000000,
                'output' => 0.30 / 1000000,
            ],
            'gemini-2.0-flash' => [
                'input' => 0.075 / 1000000,
                'output' => 0.30 / 1000000,
            ],
            'text-embedding-004' => [
                'input' => 0.025 / 1000000,
                'output' => 0.0,
            ],
        ];

        $modelKey = str_replace('models/', '', $modelName);
        $rate = $pricing[$modelKey] ?? [
            'input' => 0.075 / 1000000,
            'output' => 0.30 / 1000000,
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
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Prevent monitoring errors from crashing the main application flow
            Log::error('Failed to write AI usage log: '.$e->getMessage());
        }
    }

    /**
     * Types d'appels IA comptabilisés dans le quota utilisateur (Assistant IA :
     * chat BTP + recherche RAG). L'embedding technique n'est pas décompté.
     */
    private const QUOTA_ACTIONS = ['chat', 'search'];

    /**
     * L'utilisateur est-il dans les clous de son quota IA ?
     *
     * Cascade : IA globalement désactivée → utilisateur bloqué → limite
     * journalière effective (surcharge utilisateur sinon globale) → limite
     * mensuelle effective. `null`/0 = illimité.
     */
    public static function checkUserLimit(?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();

        // IA complètement désactivée : personne ne passe.
        $aiEnabled = DB::table('ai_settings')->where('key', 'ai_enabled')->value('value');
        if ($aiEnabled !== null && (int) $aiEnabled === 0) {
            return false;
        }

        if (! $userId) {
            return true; // Session non authentifiée : pas de quota par utilisateur.
        }

        $override = self::userOverride($userId);
        if ($override && $override->blocked) {
            return false;
        }

        $dailyLimit = self::effectiveLimit($override?->daily_limit, 'daily_user_limit');
        if ($dailyLimit > 0 && self::countUsage($userId, now()->subDay()) >= $dailyLimit) {
            return false;
        }

        $monthlyLimit = self::effectiveLimit($override?->monthly_limit, 'monthly_user_limit');
        if ($monthlyLimit > 0 && self::countUsage($userId, now()->startOfMonth()) >= $monthlyLimit) {
            return false;
        }

        return true;
    }

    /**
     * Consommation et limites effectives d'un utilisateur, pour affichage
     * (réponse de l'API chat, backoffice). Les limites valent `null` si illimité.
     *
     * @return array{daily_used:int,daily_limit:int|null,monthly_used:int,monthly_limit:int|null,blocked:bool}
     */
    public static function remainingFor(int $userId): array
    {
        $override = self::userOverride($userId);
        $dailyLimit = self::effectiveLimit($override?->daily_limit, 'daily_user_limit');
        $monthlyLimit = self::effectiveLimit($override?->monthly_limit, 'monthly_user_limit');

        return [
            'daily_used' => self::countUsage($userId, now()->subDay()),
            'daily_limit' => $dailyLimit > 0 ? $dailyLimit : null,
            'monthly_used' => self::countUsage($userId, now()->startOfMonth()),
            'monthly_limit' => $monthlyLimit > 0 ? $monthlyLimit : null,
            'blocked' => (bool) ($override?->blocked ?? false),
        ];
    }

    private static function userOverride(int $userId): ?object
    {
        return DB::table('ai_user_quotas')->where('user_id', $userId)->first();
    }

    private static function effectiveLimit(?int $override, string $globalKey): int
    {
        if ($override !== null) {
            return $override; // 0 = illimité, >0 = plafond spécifique.
        }

        return (int) DB::table('ai_settings')->where('key', $globalKey)->value('value');
    }

    private static function countUsage(int $userId, \DateTimeInterface $since): int
    {
        return DB::table('ai_usage_logs')
            ->where('user_id', $userId)
            ->whereIn('action_type', self::QUOTA_ACTIONS)
            ->where('status_code', 200)
            ->where('created_at', '>=', $since)
            ->count();
    }
}
