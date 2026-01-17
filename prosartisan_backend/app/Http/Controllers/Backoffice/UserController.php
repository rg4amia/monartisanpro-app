<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Identity\Models\ValueObjects\AccountStatus;

class UserController extends Controller
{
 public function index(Request $request)
 {
  $query = User::query()
   ->select([
    'users.*',
    'artisan_profiles.trade_category',
    'artisan_profiles.is_kyc_verified',
    'fournisseur_profiles.business_name',
    'kyc_verifications.verification_status',
    'kyc_verifications.id_type',
    'kyc_verifications.verified_at'
   ])
   ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
   ->leftJoin('fournisseur_profiles', 'users.id', '=', 'fournisseur_profiles.user_id')
   ->leftJoin('kyc_verifications', 'users.id', '=', 'kyc_verifications.user_id');

  // Apply filters
  if ($request->filled('user_type')) {
   $query->where('users.user_type', $request->user_type);
  }

  if ($request->filled('account_status')) {
   $query->where('users.account_status', $request->account_status);
  }

  if ($request->filled('kyc_status')) {
   if ($request->kyc_status === 'verified') {
    $query->where('artisan_profiles.is_kyc_verified', true);
   } elseif ($request->kyc_status === 'pending') {
    $query->where('artisan_profiles.is_kyc_verified', false)
     ->whereNotNull('kyc_verifications.id');
   } elseif ($request->kyc_status === 'not_submitted') {
    $query->whereNull('kyc_verifications.id');
   }
  }

  if ($request->filled('search')) {
   $search = $request->search;
   $query->where(function ($q) use ($search) {
    $q->where('users.email', 'like', "%{$search}%")
     ->orWhere('fournisseur_profiles.business_name', 'like', "%{$search}%");
   });
  }

  $users = $query->orderBy('users.created_at', 'desc')
   ->paginate(20)
   ->withQueryString();

  return Inertia::render('Backoffice/Users/Index', [
   'users' => $users,
   'filters' => $request->only(['user_type', 'account_status', 'kyc_status', 'search']),
   'userTypes' => $this->getUserTypes(),
   'accountStatuses' => $this->getAccountStatuses(),
  ]);
 }

 public function show(User $user)
 {
  $user->load([
   'artisanProfile',
   'fournisseurProfile',
   'kycVerification'
  ]);

  // Get user activity stats
  $stats = $this->getUserStats($user->id);

  return Inertia::render('Backoffice/Users/Show', [
   'user' => $user,
   'stats' => $stats,
  ]);
 }

 public function suspend(Request $request, User $user)
 {
  $request->validate([
   'reason' => 'required|string|max:500',
  ]);

  $user->update([
   'account_status' => AccountStatus::SUSPENDED->value,
  ]);

  // Log the suspension
  DB::table('user_activity_logs')->insert([
   'user_id' => $user->id,
   'action' => 'account_suspended',
   'details' => json_encode([
    'reason' => $request->reason,
    'suspended_by' => auth()->id(),
    'suspended_at' => now(),
   ]),
   'created_at' => now(),
  ]);

  return back()->with('success', 'Compte suspendu avec succès.');
 }

 public function activate(User $user)
 {
  $user->update([
   'account_status' => AccountStatus::ACTIVE->value,
  ]);

  // Log the activation
  DB::table('user_activity_logs')->insert([
   'user_id' => $user->id,
   'action' => 'account_activated',
   'details' => json_encode([
    'activated_by' => auth()->id(),
    'activated_at' => now(),
   ]),
   'created_at' => now(),
  ]);

  return back()->with('success', 'Compte activé avec succès.');
 }

 public function approveKyc(User $user)
 {
  if ($user->user_type === UserType::ARTISAN->value) {
   DB::table('artisan_profiles')
    ->where('user_id', $user->id)
    ->update(['is_kyc_verified' => true]);
  }

  DB::table('kyc_verifications')
   ->where('user_id', $user->id)
   ->update([
    'verification_status' => 'APPROVED',
    'verified_at' => now(),
   ]);

  // Log the KYC approval
  DB::table('user_activity_logs')->insert([
   'user_id' => $user->id,
   'action' => 'kyc_approved',
   'details' => json_encode([
    'approved_by' => auth()->id(),
    'approved_at' => now(),
   ]),
   'created_at' => now(),
  ]);

  return back()->with('success', 'KYC approuvé avec succès.');
 }

 public function rejectKyc(Request $request, User $user)
 {
  $request->validate([
   'reason' => 'required|string|max:500',
  ]);

  DB::table('kyc_verifications')
   ->where('user_id', $user->id)
   ->update([
    'verification_status' => 'REJECTED',
   ]);

  // Log the KYC rejection
  DB::table('user_activity_logs')->insert([
   'user_id' => $user->id,
   'action' => 'kyc_rejected',
   'details' => json_encode([
    'reason' => $request->reason,
    'rejected_by' => auth()->id(),
    'rejected_at' => now(),
   ]),
   'created_at' => now(),
  ]);

  return back()->with('success', 'KYC rejeté avec succès.');
 }

 private function getUserTypes()
 {
  return [
   ['value' => UserType::CLIENT->value, 'label' => 'Client'],
   ['value' => UserType::ARTISAN->value, 'label' => 'Artisan'],
   ['value' => UserType::FOURNISSEUR->value, 'label' => 'Fournisseur'],
   ['value' => UserType::REFERENT_ZONE->value, 'label' => 'Référent de Zone'],
  ];
 }

 private function getAccountStatuses()
 {
  return [
   ['value' => AccountStatus::PENDING->value, 'label' => 'En attente'],
   ['value' => AccountStatus::ACTIVE->value, 'label' => 'Actif'],
   ['value' => AccountStatus::SUSPENDED->value, 'label' => 'Suspendu'],
  ];
 }

 private function getUserStats($userId)
 {
  return [
   'missions_created' => DB::table('missions')->where('client_id', $userId)->count(),
   'quotes_submitted' => DB::table('devis')->where('artisan_id', $userId)->count(),
   'chantiers_completed' => DB::table('chantiers')
    ->where('artisan_id', $userId)
    ->where('status', 'COMPLETED')
    ->count(),
   'total_transactions' => DB::table('transactions')
    ->where(function ($query) use ($userId) {
     $query->where('from_user_id', $userId)
      ->orWhere('to_user_id', $userId);
    })
    ->where('status', 'COMPLETED')
    ->sum('amount_centimes'),
   'disputes_involved' => DB::table('litiges')
    ->where(function ($query) use ($userId) {
     $query->where('reporter_id', $userId)
      ->orWhere('defendant_id', $userId);
    })
    ->count(),
  ];
 }
}
