<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
  public function index(Request $request)
  {
    $period = $request->get('period', '30'); // Default 30 days
    $startDate = Carbon::now()->subDays($period);

    // Get comprehensive analytics
    $analytics = [
      'overview' => $this->getOverviewStats($startDate),
      'userGrowth' => $this->getUserGrowthData($startDate),
      'transactionTrends' => $this->getTransactionTrends($startDate),
      'missionStats' => $this->getMissionStats($startDate),
      'reputationDistribution' => $this->getReputationDistribution(),
      'geographicData' => $this->getGeographicData(),
      'topArtisans' => $this->getTopArtisans(),
      'topClients' => $this->getTopClients(),
    ];

    return Inertia::render('Backoffice/Analytics/Index', [
      'analytics' => $analytics,
      'period' => $period,
    ]);
  }

  public function revenue(Request $request)
  {
    $period = $request->get('period', '30');
    $startDate = Carbon::now()->subDays($period);

    $revenueData = [
      'totalRevenue' => $this->getTotalRevenue($startDate),
      'revenueByType' => $this->getRevenueByType($startDate),
      'revenueGrowth' => $this->getRevenueGrowth($startDate),
      'commissionData' => $this->getCommissionData($startDate),
      'paymentMethods' => $this->getPaymentMethodStats($startDate),
    ];

    return Inertia::render('Backoffice/Analytics/Revenue', [
      'revenueData' => $revenueData,
      'period' => $period,
    ]);
  }

  public function users(Request $request)
  {
    $period = $request->get('period', '30');
    $startDate = Carbon::now()->subDays($period);

    $userData = [
      'userStats' => $this->getUserStats($startDate),
      'userActivity' => $this->getUserActivity($startDate),
      'kycStats' => $this->getKycStats($startDate),
      'userRetention' => $this->getUserRetention($startDate),
      'userSegmentation' => $this->getUserSegmentation(),
    ];

    return Inertia::render('Backoffice/Analytics/Users', [
      'userData' => $userData,
      'period' => $period,
    ]);
  }

  public function performance(Request $request)
  {
    $period = $request->get('period', '30');
    $startDate = Carbon::now()->subDays($period);

    $performanceData = [
      'platformMetrics' => $this->getPlatformMetrics($startDate),
      'conversionRates' => $this->getConversionRates($startDate),
      'averageValues' => $this->getAverageValues($startDate),
      'completionRates' => $this->getCompletionRates($startDate),
      'disputeMetrics' => $this->getDisputeMetrics($startDate),
    ];

    return Inertia::render('Backoffice/Analytics/Performance', [
      'performanceData' => $performanceData,
      'period' => $period,
    ]);
  }

  private function getOverviewStats($startDate)
  {
    return [
      'totalUsers' => DB::table('users')->count(),
      'newUsers' => DB::table('users')->where('created_at', '>=', $startDate)->count(),
      'totalTransactions' => DB::table('transactions')->where('status', 'COMPLETED')->count(),
      'totalVolume' => DB::table('transactions')->where('status', 'COMPLETED')->sum('amount_centimes') ?: 0,
      'activeMissions' => DB::table('missions')->whereIn('status', ['OPEN', 'QUOTED', 'ACCEPTED'])->count(),
      'completedMissions' => DB::table('missions')->where('status', 'COMPLETED')->count(),
      'averageRating' => DB::table('ratings')->avg('score') ?: 0,
      'disputeRate' => $this->calculateDisputeRate(),
    ];
  }

  private function getUserGrowthData($startDate)
  {
    $data = [];
    $current = $startDate->copy();

    while ($current <= Carbon::now()) {
      $data[] = [
        'date' => $current->format('Y-m-d'),
        'clients' => DB::table('users')
          ->where('user_type', 'CLIENT')
          ->whereDate('created_at', $current)
          ->count(),
        'artisans' => DB::table('users')
          ->where('user_type', 'ARTISAN')
          ->whereDate('created_at', $current)
          ->count(),
        'fournisseurs' => DB::table('users')
          ->where('user_type', 'FOURNISSEUR')
          ->whereDate('created_at', $current)
          ->count(),
      ];
      $current->addDay();
    }

    return $data;
  }

  private function getTransactionTrends($startDate)
  {
    $data = [];
    $current = $startDate->copy();

    while ($current <= Carbon::now()) {
      $dayTransactions = DB::table('transactions')
        ->whereDate('created_at', $current)
        ->where('status', 'COMPLETED');

      $data[] = [
        'date' => $current->format('Y-m-d'),
        'count' => $dayTransactions->count(),
        'volume' => $dayTransactions->sum('amount_centimes'),
        'deposits' => DB::table('transactions')
          ->whereDate('created_at', $current)
          ->where('type', 'DEPOSIT')
          ->where('status', 'COMPLETED')
          ->sum('amount_centimes'),
        'withdrawals' => DB::table('transactions')
          ->whereDate('created_at', $current)
          ->where('type', 'WITHDRAWAL')
          ->where('status', 'COMPLETED')
          ->sum('amount_centimes'),
      ];
      $current->addDay();
    }

    return $data;
  }

  private function getMissionStats($startDate)
  {
    return [
      'totalMissions' => DB::table('missions')->count(),
      'newMissions' => DB::table('missions')->where('created_at', '>=', $startDate)->count(),
      'missionsByStatus' => DB::table('missions')
        ->select('status', DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get(),
      'averageMissionValue' => DB::table('devis')
        ->where('status', 'ACCEPTED')
        ->avg('total_amount_centimes'),
      'missionCompletionRate' => $this->calculateMissionCompletionRate(),
    ];
  }

  private function getReputationDistribution()
  {
    return [
      'excellent' => DB::table('reputation_profiles')->where('current_score', '>=', 800)->count(),
      'good' => DB::table('reputation_profiles')->whereBetween('current_score', [600, 799])->count(),
      'average' => DB::table('reputation_profiles')->whereBetween('current_score', [400, 599])->count(),
      'poor' => DB::table('reputation_profiles')->where('current_score', '<', 400)->count(),
      'averageScore' => DB::table('reputation_profiles')->avg('current_score'),
    ];
  }

  private function getGeographicData()
  {
    return DB::table('referent_zone_profiles')
      ->select('zone', DB::raw('count(*) as artisan_count'))
      ->join('users', 'referent_zone_profiles.user_id', '=', 'users.id')
      ->where('users.user_type', 'ARTISAN')
      ->groupBy('zone')
      ->get();
  }

  private function getTopArtisans()
  {
    return DB::table('users')
      ->select([
        'users.email',
        'reputation_profiles.current_score',
        DB::raw('COUNT(chantiers.id) as completed_projects'),
        DB::raw('AVG(ratings.score) as average_rating'),
        DB::raw('SUM(transactions.amount_centimes) as total_earnings')
      ])
      ->join('reputation_profiles', 'users.id', '=', 'reputation_profiles.artisan_id')
      ->leftJoin('chantiers', function ($join) {
        $join->on('users.id', '=', 'chantiers.artisan_id')
          ->where('chantiers.status', '=', 'COMPLETED');
      })
      ->leftJoin('ratings', 'users.id', '=', 'ratings.rated_id')
      ->leftJoin('transactions', function ($join) {
        $join->on('users.id', '=', 'transactions.to_user_id')
          ->where('transactions.status', '=', 'COMPLETED');
      })
      ->where('users.user_type', 'ARTISAN')
      ->groupBy('users.id', 'users.email', 'reputation_profiles.current_score')
      ->orderBy('reputation_profiles.current_score', 'desc')
      ->limit(10)
      ->get();
  }

  private function getTopClients()
  {
    return DB::table('users')
      ->select([
        'users.email',
        DB::raw('COUNT(missions.id) as missions_created'),
        DB::raw('SUM(transactions.amount_centimes) as total_spent'),
        DB::raw('AVG(ratings.score) as average_rating_given')
      ])
      ->leftJoin('missions', 'users.id', '=', 'missions.client_id')
      ->leftJoin('transactions', function ($join) {
        $join->on('users.id', '=', 'transactions.from_user_id')
          ->where('transactions.status', '=', 'COMPLETED');
      })
      ->leftJoin('ratings', 'users.id', '=', 'ratings.rater_id')
      ->where('users.user_type', 'CLIENT')
      ->groupBy('users.id', 'users.email')
      ->orderBy('total_spent', 'desc')
      ->limit(10)
      ->get();
  }

  private function getTotalRevenue($startDate)
  {
    // Assuming platform takes a commission on completed transactions
    $commissionRate = 0.05; // 5% commission

    return DB::table('transactions')
      ->where('status', 'COMPLETED')
      ->where('type', 'ESCROW_RELEASE')
      ->where('created_at', '>=', $startDate)
      ->sum('amount_centimes') * $commissionRate;
  }

  private function getRevenueByType($startDate)
  {
    return DB::table('transactions')
      ->select('type', DB::raw('SUM(amount_centimes) as total'))
      ->where('status', 'COMPLETED')
      ->where('created_at', '>=', $startDate)
      ->groupBy('type')
      ->get();
  }

  private function getRevenueGrowth($startDate)
  {
    $data = [];
    $current = $startDate->copy();
    $commissionRate = 0.05;

    while ($current <= Carbon::now()) {
      $revenue = DB::table('transactions')
        ->where('status', 'COMPLETED')
        ->where('type', 'ESCROW_RELEASE')
        ->whereDate('created_at', $current)
        ->sum('amount_centimes') * $commissionRate;

      $data[] = [
        'date' => $current->format('Y-m-d'),
        'revenue' => $revenue,
      ];
      $current->addDay();
    }

    return $data;
  }

  private function getCommissionData($startDate)
  {
    return [
      'totalCommissions' => $this->getTotalRevenue($startDate),
      'averageCommissionPerTransaction' => $this->getTotalRevenue($startDate) / max(1, DB::table('transactions')
        ->where('status', 'COMPLETED')
        ->where('type', 'ESCROW_RELEASE')
        ->where('created_at', '>=', $startDate)
        ->count()),
    ];
  }

  private function getPaymentMethodStats($startDate)
  {
    return DB::table('transactions')
      ->select(
        DB::raw('JSON_EXTRACT(metadata, "$.gateway") as gateway'),
        DB::raw('COUNT(*) as count'),
        DB::raw('SUM(amount_centimes) as volume')
      )
      ->where('status', 'COMPLETED')
      ->where('created_at', '>=', $startDate)
      ->whereNotNull('metadata')
      ->groupBy('gateway')
      ->get();
  }

  private function getUserStats($startDate)
  {
    return [
      'totalUsers' => DB::table('users')->count(),
      'newUsers' => DB::table('users')->where('created_at', '>=', $startDate)->count(),
      'activeUsers' => DB::table('users')->where('account_status', 'ACTIVE')->count(),
      'verifiedArtisans' => DB::table('artisan_profiles')->where('is_kyc_verified', true)->count(),
    ];
  }

  private function getUserActivity($startDate)
  {
    return DB::table('user_activity_logs')
      ->select('action', DB::raw('COUNT(*) as count'))
      ->where('created_at', '>=', $startDate)
      ->groupBy('action')
      ->get();
  }

  private function getKycStats($startDate)
  {
    return [
      'totalSubmissions' => DB::table('kyc_verifications')->count(),
      'newSubmissions' => DB::table('kyc_verifications')->where('created_at', '>=', $startDate)->count(),
      'approved' => DB::table('kyc_verifications')->where('verification_status', 'APPROVED')->count(),
      'rejected' => DB::table('kyc_verifications')->where('verification_status', 'REJECTED')->count(),
      'pending' => DB::table('kyc_verifications')->where('verification_status', 'PENDING')->count(),
    ];
  }

  private function getUserRetention($startDate)
  {
    // Calculate user retention rate (simplified)
    $totalUsers = DB::table('users')->where('created_at', '<', $startDate)->count();
    $activeUsers = DB::table('user_activity_logs')
      ->distinct('user_id')
      ->where('created_at', '>=', $startDate)
      ->count();

    return $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;
  }

  private function getUserSegmentation()
  {
    return [
      'byType' => DB::table('users')
        ->select('user_type', DB::raw('COUNT(*) as count'))
        ->groupBy('user_type')
        ->get(),
      'byStatus' => DB::table('users')
        ->select('account_status', DB::raw('COUNT(*) as count'))
        ->groupBy('account_status')
        ->get(),
    ];
  }

  private function getPlatformMetrics($startDate)
  {
    return [
      'totalMissions' => DB::table('missions')->count(),
      'completedMissions' => DB::table('missions')->where('status', 'COMPLETED')->count(),
      'averageCompletionTime' => $this->calculateAverageCompletionTime(),
      'totalTransactionVolume' => DB::table('transactions')->where('status', 'COMPLETED')->sum('amount_centimes'),
    ];
  }

  private function getConversionRates($startDate)
  {
    $totalMissions = DB::table('missions')->where('created_at', '>=', $startDate)->count();
    $quotedMissions = DB::table('missions')->where('status', 'QUOTED')->where('created_at', '>=', $startDate)->count();
    $acceptedMissions = DB::table('missions')->where('status', 'ACCEPTED')->where('created_at', '>=', $startDate)->count();

    return [
      'missionToQuote' => $totalMissions > 0 ? ($quotedMissions / $totalMissions) * 100 : 0,
      'quoteToAcceptance' => $quotedMissions > 0 ? ($acceptedMissions / $quotedMissions) * 100 : 0,
    ];
  }

  private function getAverageValues($startDate)
  {
    return [
      'averageMissionValue' => DB::table('devis')->where('status', 'ACCEPTED')->avg('total_amount_centimes') ?: 0,
      'averageTransactionValue' => DB::table('transactions')->where('status', 'COMPLETED')->avg('amount_centimes') ?: 0,
      'averageRating' => DB::table('ratings')->avg('score') ?: 0,
    ];
  }

  private function getCompletionRates($startDate)
  {
    $totalChantiers = DB::table('chantiers')->where('created_at', '>=', $startDate)->count();
    $completedChantiers = DB::table('chantiers')->where('status', 'COMPLETED')->where('created_at', '>=', $startDate)->count();

    return [
      'chantiersCompletionRate' => $totalChantiers > 0 ? ($completedChantiers / $totalChantiers) * 100 : 0,
    ];
  }

  private function getDisputeMetrics($startDate)
  {
    $totalMissions = DB::table('missions')->where('created_at', '>=', $startDate)->count();
    $disputedMissions = DB::table('litiges')->where('created_at', '>=', $startDate)->count();

    return [
      'totalDisputes' => $disputedMissions,
      'disputeRate' => $totalMissions > 0 ? ($disputedMissions / $totalMissions) * 100 : 0,
      'resolvedDisputes' => DB::table('litiges')->where('status', 'RESOLVED')->where('created_at', '>=', $startDate)->count(),
      'averageResolutionTime' => $this->calculateAverageDisputeResolutionTime(),
    ];
  }

  private function calculateDisputeRate()
  {
    $totalMissions = DB::table('missions')->count();
    $disputedMissions = DB::table('litiges')->count();

    return $totalMissions > 0 ? ($disputedMissions / $totalMissions) * 100 : 0;
  }

  private function calculateMissionCompletionRate()
  {
    $totalMissions = DB::table('missions')->count();
    $completedMissions = DB::table('missions')->where('status', 'COMPLETED')->count();

    return $totalMissions > 0 ? ($completedMissions / $totalMissions) * 100 : 0;
  }

  private function calculateAverageCompletionTime()
  {
    // This would need to be calculated based on mission start and completion dates
    // For now, return a placeholder
    return 15; // 15 days average
  }

  private function calculateAverageDisputeResolutionTime()
  {
    // This would need to be calculated based on dispute creation and resolution dates
    // For now, return a placeholder
    return 7; // 7 days average
  }
}
