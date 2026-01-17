<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class ReputationController extends Controller
{
 public function index(Request $request)
 {
  $query = DB::table('reputation_profiles')
   ->select([
    'reputation_profiles.*',
    'users.email',
    'artisan_profiles.trade_category',
    'artisan_profiles.is_kyc_verified'
   ])
   ->join('users', 'reputation_profiles.artisan_id', '=', 'users.id')
   ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
   ->where('users.user_type', 'ARTISAN');

  // Apply filters
  if ($request->filled('trade_category')) {
   $query->where('artisan_profiles.trade_category', $request->trade_category);
  }

  if ($request->filled('score_range')) {
   switch ($request->score_range) {
    case 'high':
     $query->where('reputation_profiles.current_score', '>=', 800);
     break;
    case 'medium':
     $query->whereBetween('reputation_profiles.current_score', [600, 799]);
     break;
    case 'low':
     $query->where('reputation_profiles.current_score', '<', 600);
     break;
   }
  }

  if ($request->filled('search')) {
   $search = $request->search;
   $query->where('users.email', 'like', "%{$search}%");
  }

  $artisans = $query->orderBy('reputation_profiles.current_score', 'desc')
   ->paginate(20)
   ->withQueryString();

  // Get statistics
  $stats = $this->getReputationStats();

  return Inertia::render('Backoffice/Reputation/Index', [
   'artisans' => $artisans,
   'filters' => $request->only(['trade_category', 'score_range', 'search']),
   'stats' => $stats,
   'tradeCategories' => $this->getTradeCategories(),
  ]);
 }

 public function show($id)
 {
  $artisan = DB::table('reputation_profiles')
   ->select([
    'reputation_profiles.*',
    'users.email',
    'users.created_at as user_created_at',
    'artisan_profiles.trade_category',
    'artisan_profiles.is_kyc_verified'
   ])
   ->join('users', 'reputation_profiles.artisan_id', '=', 'users.id')
   ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
   ->where('reputation_profiles.artisan_id', $id)
   ->first();

  if (!$artisan) {
   abort(404);
  }

  // Get score history
  $scoreHistory = DB::table('score_history')
   ->where('artisan_id', $id)
   ->orderBy('recorded_at', 'desc')
   ->limit(20)
   ->get();

  // Get detailed metrics
  $detailedMetrics = $this->getDetailedMetrics($id);

  // Get recent ratings
  $recentRatings = DB::table('ratings')
   ->select([
    'ratings.*',
    'users.email as client_email',
    'missions.description as mission_description'
   ])
   ->leftJoin('users', 'ratings.client_id', '=', 'users.id')
   ->leftJoin('missions', 'ratings.mission_id', '=', 'missions.id')
   ->where('ratings.artisan_id', $id)
   ->orderBy('ratings.created_at', 'desc')
   ->limit(10)
   ->get();

  return Inertia::render('Backoffice/Reputation/Show', [
   'artisan' => $artisan,
   'scoreHistory' => $scoreHistory,
   'detailedMetrics' => $detailedMetrics,
   'recentRatings' => $recentRatings,
  ]);
 }

 public function adjustScore(Request $request, $id)
 {
  $request->validate([
   'new_score' => 'required|integer|min:0|max:1000',
   'justification' => 'required|string|max:500',
  ]);

  DB::beginTransaction();

  try {
   // Get current score
   $currentProfile = DB::table('reputation_profiles')
    ->where('artisan_id', $id)
    ->first();

   if (!$currentProfile) {
    return back()->withErrors(['error' => 'Profil de réputation non trouvé.']);
   }

   $oldScore = $currentProfile->current_score;

   // Update the score
   DB::table('reputation_profiles')
    ->where('artisan_id', $id)
    ->update([
     'current_score' => $request->new_score,
     'last_calculated_at' => now(),
    ]);

   // Record in score history
   DB::table('score_history')->insert([
    'id' => \Illuminate\Support\Str::uuid(),
    'artisan_id' => $id,
    'old_score' => $oldScore,
    'new_score' => $request->new_score,
    'reason' => 'Manual adjustment by admin: ' . $request->justification,
    'recorded_at' => now(),
   ]);

   // Log the adjustment
   DB::table('user_activity_logs')->insert([
    'user_id' => $id,
    'action' => 'score_manually_adjusted',
    'details' => json_encode([
     'old_score' => $oldScore,
     'new_score' => $request->new_score,
     'justification' => $request->justification,
     'adjusted_by' => auth()->id(),
     'adjusted_at' => now(),
    ]),
    'created_at' => now(),
   ]);

   DB::commit();

   return back()->with('success', 'Score ajusté avec succès.');
  } catch (\Exception $e) {
   DB::rollBack();
   return back()->withErrors(['error' => 'Erreur lors de l\'ajustement du score: ' . $e->getMessage()]);
  }
 }

 public function exportTransactions(Request $request)
 {
  $query = DB::table('transactions')
   ->select([
    'transactions.*',
    'from_user.email as from_email',
    'to_user.email as to_email'
   ])
   ->leftJoin('users as from_user', 'transactions.from_user_id', '=', 'from_user.id')
   ->leftJoin('users as to_user', 'transactions.to_user_id', '=', 'to_user.id')
   ->where('transactions.status', 'COMPLETED');

  // Apply date filters
  if ($request->filled('start_date')) {
   $query->whereDate('transactions.created_at', '>=', $request->start_date);
  }

  if ($request->filled('end_date')) {
   $query->whereDate('transactions.created_at', '<=', $request->end_date);
  }

  $transactions = $query->orderBy('transactions.created_at', 'desc')->get();

  // Generate CSV
  $filename = 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';
  $headers = [
   'Content-Type' => 'text/csv',
   'Content-Disposition' => "attachment; filename=\"$filename\"",
  ];

  $callback = function () use ($transactions) {
   $file = fopen('php://output', 'w');

   // CSV headers
   fputcsv($file, [
    'ID Transaction',
    'De (Email)',
    'Vers (Email)',
    'Montant (XOF)',
    'Type',
    'Statut',
    'Référence Mobile Money',
    'Date de création',
    'Date de completion'
   ]);

   // CSV data
   foreach ($transactions as $transaction) {
    fputcsv($file, [
     $transaction->id,
     $transaction->from_email,
     $transaction->to_email,
     $transaction->amount_centimes / 100,
     $transaction->type,
     $transaction->status,
     $transaction->mobile_money_reference,
     $transaction->created_at,
     $transaction->completed_at
    ]);
   }

   fclose($file);
  };

  return response()->stream($callback, 200, $headers);
 }

 private function getReputationStats()
 {
  return [
   'total_artisans' => DB::table('reputation_profiles')->count(),
   'high_score_artisans' => DB::table('reputation_profiles')->where('current_score', '>=', 800)->count(),
   'medium_score_artisans' => DB::table('reputation_profiles')->whereBetween('current_score', [600, 799])->count(),
   'low_score_artisans' => DB::table('reputation_profiles')->where('current_score', '<', 600)->count(),
   'average_score' => DB::table('reputation_profiles')->avg('current_score'),
   'micro_credit_eligible' => DB::table('reputation_profiles')->where('current_score', '>', 700)->count(),
  ];
 }

 private function getDetailedMetrics($artisanId)
 {
  return [
   'completed_projects' => DB::table('chantiers')
    ->where('artisan_id', $artisanId)
    ->where('status', 'COMPLETED')
    ->count(),

   'accepted_projects' => DB::table('devis')
    ->where('artisan_id', $artisanId)
    ->where('status', 'ACCEPTED')
    ->count(),

   'total_earnings' => DB::table('transactions')
    ->where('to_user_id', $artisanId)
    ->where('status', 'COMPLETED')
    ->where('type', 'LABOR_RELEASE')
    ->sum('amount_centimes'),

   'average_rating' => DB::table('ratings')
    ->where('artisan_id', $artisanId)
    ->avg('rating'),

   'total_ratings' => DB::table('ratings')
    ->where('artisan_id', $artisanId)
    ->count(),

   'disputes_involved' => DB::table('litiges')
    ->where(function ($query) use ($artisanId) {
     $query->where('reporter_id', $artisanId)
      ->orWhere('defendant_id', $artisanId);
    })
    ->count(),

   'response_time_hours' => $this->calculateAverageResponseTime($artisanId),
  ];
 }

 private function calculateAverageResponseTime($artisanId)
 {
  // This would need to be implemented based on notification/response tracking
  // For now, return a placeholder
  return 2.5;
 }

 private function getTradeCategories()
 {
  return [
   ['value' => 'PLUMBER', 'label' => 'Plombier'],
   ['value' => 'ELECTRICIAN', 'label' => 'Électricien'],
   ['value' => 'MASON', 'label' => 'Maçon'],
  ];
 }
}
