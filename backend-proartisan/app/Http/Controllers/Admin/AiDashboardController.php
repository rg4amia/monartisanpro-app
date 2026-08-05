<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiDashboardController extends Controller
{
    /**
     * Display the AI monitoring dashboard.
     */
    public function index(): Response
    {
        // 1. Statistics
        $totalCost = DB::table('ai_usage_logs')->sum('estimated_cost_usd') ?? 0;
        $totalRequests = DB::table('ai_usage_logs')->count();
        $averageResponseTime = DB::table('ai_usage_logs')->avg('response_time_ms') ?? 0;
        $successRate = $totalRequests > 0 
            ? (DB::table('ai_usage_logs')->where('status_code', 200)->count() / $totalRequests) * 100 
            : 100;

        // 2. Costs by model
        $costsByModel = DB::table('ai_usage_logs')
            ->select('model_name', DB::raw('SUM(estimated_cost_usd) as cost'), DB::raw('COUNT(*) as count'))
            ->groupBy('model_name')
            ->get();

        // 3. Last 50 logs
        $logs = DB::table('ai_usage_logs')
            ->leftJoin('users', 'ai_usage_logs.user_id', '=', 'users.id')
            ->select('ai_usage_logs.*', 'users.email as user_email')
            ->orderBy('ai_usage_logs.created_at', 'desc')
            ->limit(50)
            ->get();

        // 4. Daily consumption for the last 30 days
        $dailyUsage = DB::table('ai_usage_logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(estimated_cost_usd) as cost'),
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('COUNT(*) as requests')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->limit(30)
            ->get();

        // 5. Settings
        $settings = DB::table('ai_settings')->pluck('value', 'key');

        return Inertia::render('admin/ai-dashboard', [
            'stats' => [
                'total_cost' => (float)$totalCost,
                'total_requests' => $totalRequests,
                'avg_response_time' => round($averageResponseTime, 2),
                'success_rate' => round($successRate, 1)
            ],
            'costsByModel' => $costsByModel,
            'dailyUsage' => $dailyUsage,
            'logs' => $logs,
            'settings' => $settings
        ]);
    }

    /**
     * Update AI configuration settings.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'daily_user_limit' => 'required|integer|min:0',
            'ai_enabled' => 'required|in:0,1'
        ]);

        foreach ($validated as $key => $value) {
            DB::table('ai_settings')
                ->updateOrInsert(
                    ['key' => $key],
                    ['value' => (string)$value, 'updated_at' => now()]
                );
        }

        return redirect()->back()->with('success', 'Paramètres IA mis à jour avec succès.');
    }
}
