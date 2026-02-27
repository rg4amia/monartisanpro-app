<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TokenRedemption;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    /**
     * Get vendor statistics
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'fournisseur') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Current month stats
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $tokensValidated = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startOfMonth, $endOfMonth])
            ->count();

        $totalAmount = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $pendingAmount = Transaction::where('type', 'token_redemption')
            ->whereHas('tokenRedemption', function ($q) use ($user) {
                $q->where('vendor_id', $user->id);
            })
            ->where('status', 'pending')
            ->sum('amount');

        $completedAmount = Transaction::where('type', 'token_redemption')
            ->whereHas('tokenRedemption', function ($q) use ($user) {
                $q->where('vendor_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        return response()->json([
            'tokens_validated' => $tokensValidated,
            'total_amount' => $totalAmount,
            'pending_amount' => $pendingAmount,
            'completed_amount' => $completedAmount,
            'period' => [
                'start' => $startOfMonth->toDateString(),
                'end' => $endOfMonth->toDateString(),
            ],
        ]);
    }

    /**
     * Get vendor redemption history
     */
    public function redemptions(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'fournisseur') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = TokenRedemption::with(['token', 'vendor'])
            ->where('vendor_id', $user->id);

        // Filter by validation method
        if ($request->has('validation_method')) {
            $query->where('validation_method', $request->validation_method);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('redeemed_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('redeemed_at', '<=', $request->end_date);
        }

        $redemptions = $query->orderBy('redeemed_at', 'desc')->paginate(20);

        return response()->json($redemptions);
    }

    /**
     * Get vendor transactions
     */
    public function transactions(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'fournisseur') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Transaction::where('type', 'token_redemption')
            ->whereHas('tokenRedemption', function ($q) use ($user) {
                $q->where('vendor_id', $user->id);
            });

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Get vendor analytics
     */
    public function analytics(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'fournisseur') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $period = $request->input('period', 'month'); // week, month, year

        // Determine date range
        switch ($period) {
            case 'week':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                $groupBy = 'DATE(redeemed_at)';
                break;
            case 'year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                $groupBy = 'MONTH(redeemed_at)';
                break;
            default: // month
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $groupBy = 'DATE(redeemed_at)';
        }

        // Validation trend
        $validationTrend = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->select(
                DB::raw($groupBy . ' as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Validation methods breakdown
        $methodsBreakdown = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->select('validation_method', DB::raw('COUNT(*) as count'))
            ->groupBy('validation_method')
            ->get();

        // Top projects
        $topProjects = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->join('material_tokens', 'token_redemptions.token_id', '=', 'material_tokens.id')
            ->join('projects', 'material_tokens.project_id', '=', 'projects.id')
            ->select(
                'projects.id',
                'projects.title',
                DB::raw('COUNT(*) as redemption_count'),
                DB::raw('SUM(token_redemptions.amount) as total_amount')
            )
            ->groupBy('projects.id', 'projects.title')
            ->orderBy('total_amount', 'desc')
            ->limit(5)
            ->get();

        // Average validation amount
        $averageAmount = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->avg('amount');

        // Validation rate (GPS vs OTP)
        $totalValidations = TokenRedemption::where('vendor_id', $user->id)
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->count();

        $gpsValidations = TokenRedemption::where('vendor_id', $user->id)
            ->where('validation_method', 'gps')
            ->whereBetween('redeemed_at', [$startDate, $endDate])
            ->count();

        $validationRate = $totalValidations > 0 ? ($gpsValidations / $totalValidations) * 100 : 0;

        return response()->json([
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'validation_trend' => $validationTrend,
            'methods_breakdown' => $methodsBreakdown,
            'top_projects' => $topProjects,
            'average_amount' => round($averageAmount, 2),
            'validation_rate' => round($validationRate, 2),
            'total_validations' => $totalValidations,
        ]);
    }
}
