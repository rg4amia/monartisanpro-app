<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Get dashboard statistics
        $stats = $this->getDashboardStats();

        // Get chart data for the last 7 days
        $chartData = $this->getTransactionChartData();

        // Get dispute statistics
        $disputeStats = $this->getDisputeStats();

        return Inertia::render('Backoffice/Dashboard/Index', [
            'stats' => $stats,
            'chartData' => $chartData,
            'disputeStats' => $disputeStats,
        ]);
    }

    private function getDashboardStats()
    {
        return [
            'artisans_actifs' => DB::table('users')
                ->join('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
                ->where('users.user_type', 'ARTISAN')
                ->where('users.account_status', 'ACTIVE')
                ->where('artisan_profiles.is_kyc_verified', true)
                ->count(),

            'missions_en_cours' => DB::table('missions')
                ->whereIn('status', ['OPEN', 'QUOTED', 'ACCEPTED'])
                ->count(),

            'volume_transactions' => DB::table('transactions')
                ->where('status', 'COMPLETED')
                ->whereDate('created_at', Carbon::today())
                ->sum('amount_centimes'),

            'chantiers_termines' => DB::table('chantiers')
                ->where('status', 'COMPLETED')
                ->whereMonth('completed_at', Carbon::now()->month)
                ->count(),

            'litiges_actifs' => DB::table('litiges')
                ->whereIn('status', ['OPEN', 'IN_MEDIATION', 'IN_ARBITRATION'])
                ->count(),
        ];
    }

    private function getTransactionChartData()
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);

            $montant = DB::table('transactions')
                ->where('status', 'COMPLETED')
                ->whereDate('created_at', $date)
                ->sum('amount_centimes');

            $data[] = [
                'date' => $date->format('d/m'),
                'montant' => $montant / 100, // Convert from centimes to XOF
            ];
        }

        return $data;
    }

    private function getDisputeStats()
    {
        return DB::table('litiges')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $this->translateDisputeStatus($item->status),
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    private function translateDisputeStatus($status)
    {
        $translations = [
            'OPEN' => 'Ouvert',
            'IN_MEDIATION' => 'En médiation',
            'IN_ARBITRATION' => 'En arbitrage',
            'RESOLVED' => 'Résolu',
            'CLOSED' => 'Fermé',
        ];

        return $translations[$status] ?? $status;
    }
}
