<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArtisanScore;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArtisanController extends Controller
{
 /**
  * Get artisan statistics
  */
 public function stats(Request $request)
 {
  $user = $request->user();

  if ($user->role !== 'artisan') {
   return response()->json(['error' => 'Unauthorized'], 403);
  }

  // Projects stats
  $activeProjects = Project::where('artisan_id', $user->id)
   ->where('status', 'in_progress')
   ->count();

  $completedProjects = Project::where('artisan_id', $user->id)
   ->where('status', 'completed')
   ->count();

  $totalProjects = Project::where('artisan_id', $user->id)->count();

  // Quotes stats
  $pendingQuotes = Quote::where('artisan_id', $user->id)
   ->where('status', 'pending')
   ->count();

  $acceptedQuotes = Quote::where('artisan_id', $user->id)
   ->where('status', 'accepted')
   ->count();

  $totalQuotes = Quote::where('artisan_id', $user->id)->count();

  // Financial stats
  $totalEarnings = Transaction::where('type', 'labor_release')
   ->whereHas('project', function ($q) use ($user) {
    $q->where('artisan_id', $user->id);
   })
   ->where('status', 'completed')
   ->sum('amount');

  $pendingPayments = Transaction::where('type', 'labor_release')
   ->whereHas('project', function ($q) use ($user) {
    $q->where('artisan_id', $user->id);
   })
   ->where('status', 'pending')
   ->sum('amount');

  // Score
  $score = ArtisanScore::where('artisan_id', $user->id)->first();

  return response()->json([
   'projects' => [
    'active' => $activeProjects,
    'completed' => $completedProjects,
    'total' => $totalProjects,
   ],
   'quotes' => [
    'pending' => $pendingQuotes,
    'accepted' => $acceptedQuotes,
    'total' => $totalQuotes,
   ],
   'earnings' => [
    'total' => $totalEarnings,
    'pending' => $pendingPayments,
   ],
   'score' => $score ? [
    'total' => $score->total_score,
    'badge' => $score->badge_level,
   ] : null,
  ]);
 }

 /**
  * Get artisan quotes
  */
 public function quotes(Request $request)
 {
  $user = $request->user();

  if ($user->role !== 'artisan') {
   return response()->json(['error' => 'Unauthorized'], 403);
  }

  $query = Quote::with(['project', 'items'])
   ->where('artisan_id', $user->id);

  // Filter by status
  if ($request->has('status') && $request->status !== 'all') {
   $query->where('status', $request->status);
  }

  $quotes = $query->orderBy('created_at', 'desc')->paginate(20);

  return response()->json($quotes);
 }

 /**
  * Get artisan transactions
  */
 public function transactions(Request $request)
 {
  $user = $request->user();

  if ($user->role !== 'artisan') {
   return response()->json(['error' => 'Unauthorized'], 403);
  }

  $query = Transaction::with(['project'])
   ->where('type', 'labor_release')
   ->whereHas('project', function ($q) use ($user) {
    $q->where('artisan_id', $user->id);
   });

  // Filter by status
  if ($request->has('status')) {
   $query->where('status', $request->status);
  }

  // Filter by type
  if ($request->has('transaction_type')) {
   $query->where('type', $request->transaction_type);
  }

  $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

  return response()->json($transactions);
 }

 /**
  * Get artisan score details
  */
 public function score(Request $request)
 {
  $user = $request->user();

  if ($user->role !== 'artisan') {
   return response()->json(['error' => 'Unauthorized'], 403);
  }

  $score = ArtisanScore::with('scoreHistories')
   ->where('artisan_id', $user->id)
   ->first();

  if (!$score) {
   return response()->json([
    'message' => 'Score not yet calculated',
    'score' => null,
   ]);
  }

  return response()->json($score);
 }
}
