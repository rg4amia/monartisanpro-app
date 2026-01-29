<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KYCController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('kyc_verifications')
            ->select([
                'kyc_verifications.*',
                'users.email',
                'users.user_type',
                'artisan_profiles.trade_category',
            ])
            ->join('users', 'kyc_verifications.user_id', '=', 'users.id')
            ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('kyc_verifications.verification_status', $request->status);
        }

        if ($request->filled('id_type')) {
            $query->where('kyc_verifications.id_type', $request->id_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.email', 'like', "%{$search}%")
                    ->orWhere('kyc_verifications.id_number', 'like', "%{$search}%");
            });
        }

        $verifications = $query->orderBy('kyc_verifications.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get statistics
        $stats = $this->getKYCStats();

        return Inertia::render('Backoffice/KYC/Index', [
            'verifications' => $verifications,
            'filters' => $request->only(['status', 'id_type', 'search']),
            'stats' => $stats,
            'verificationStatuses' => $this->getVerificationStatuses(),
            'idTypes' => $this->getIdTypes(),
        ]);
    }

    public function pending(Request $request)
    {
        $query = DB::table('kyc_verifications')
            ->select([
                'kyc_verifications.*',
                'users.email',
                'users.user_type',
                'artisan_profiles.trade_category',
            ])
            ->join('users', 'kyc_verifications.user_id', '=', 'users.id')
            ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
            ->where('kyc_verifications.verification_status', 'PENDING');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.email', 'like', "%{$search}%")
                    ->orWhere('kyc_verifications.id_number', 'like', "%{$search}%");
            });
        }

        $pendingVerifications = $query->orderBy('kyc_verifications.created_at', 'asc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Backoffice/KYC/Pending', [
            'verifications' => $pendingVerifications,
            'filters' => $request->only(['search']),
        ]);
    }

    public function approved(Request $request)
    {
        $query = DB::table('kyc_verifications')
            ->select([
                'kyc_verifications.*',
                'users.email',
                'users.user_type',
                'artisan_profiles.trade_category',
            ])
            ->join('users', 'kyc_verifications.user_id', '=', 'users.id')
            ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
            ->where('kyc_verifications.verification_status', 'APPROVED');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.email', 'like', "%{$search}%")
                    ->orWhere('kyc_verifications.id_number', 'like', "%{$search}%");
            });
        }

        $approvedVerifications = $query->orderBy('kyc_verifications.verified_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Backoffice/KYC/Approved', [
            'verifications' => $approvedVerifications,
            'filters' => $request->only(['search']),
        ]);
    }

    public function rejected(Request $request)
    {
        $query = DB::table('kyc_verifications')
            ->select([
                'kyc_verifications.*',
                'users.email',
                'users.user_type',
                'artisan_profiles.trade_category',
            ])
            ->join('users', 'kyc_verifications.user_id', '=', 'users.id')
            ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
            ->where('kyc_verifications.verification_status', 'REJECTED');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.email', 'like', "%{$search}%")
                    ->orWhere('kyc_verifications.id_number', 'like', "%{$search}%");
            });
        }

        $rejectedVerifications = $query->orderBy('kyc_verifications.updated_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Backoffice/KYC/Rejected', [
            'verifications' => $rejectedVerifications,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show($id)
    {
        $verification = DB::table('kyc_verifications')
            ->select([
                'kyc_verifications.*',
                'users.email',
                'users.user_type',
                'users.phone_number',
                'users.created_at as user_created_at',
                'artisan_profiles.trade_category',
                'artisan_profiles.is_kyc_verified',
            ])
            ->join('users', 'kyc_verifications.user_id', '=', 'users.id')
            ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id')
            ->where('kyc_verifications.id', $id)
            ->first();

        if (!$verification) {
            abort(404);
        }

        // Get verification history
        $history = DB::table('user_activity_logs')
            ->where('user_id', $verification->user_id)
            ->whereIn('action', ['kyc_submitted', 'kyc_approved', 'kyc_rejected'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Backoffice/KYC/Show', [
            'verification' => $verification,
            'history' => $history,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $verification = DB::table('kyc_verifications')->where('id', $id)->first();

        if (!$verification) {
            abort(404);
        }

        DB::beginTransaction();

        try {
            // Update verification status
            DB::table('kyc_verifications')
                ->where('id', $id)
                ->update([
                    'verification_status' => 'APPROVED',
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

            // Update artisan profile if applicable
            $user = DB::table('users')->where('id', $verification->user_id)->first();
            if ($user && $user->user_type === 'ARTISAN') {
                DB::table('artisan_profiles')
                    ->where('user_id', $verification->user_id)
                    ->update(['is_kyc_verified' => true]);
            }

            // Log the approval
            DB::table('user_activity_logs')->insert([
                'user_id' => $verification->user_id,
                'action' => 'kyc_approved',
                'details' => json_encode([
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'verification_id' => $id,
                ]),
                'created_at' => now(),
            ]);

            DB::commit();

            return back()->with('success', 'Vérification KYC approuvée avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Erreur lors de l\'approbation: ' . $e->getMessage()]);
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $verification = DB::table('kyc_verifications')->where('id', $id)->first();

        if (!$verification) {
            abort(404);
        }

        DB::beginTransaction();

        try {
            // Update verification status
            DB::table('kyc_verifications')
                ->where('id', $id)
                ->update([
                    'verification_status' => 'REJECTED',
                    'rejection_reason' => $request->reason,
                    'updated_at' => now(),
                ]);

            // Log the rejection
            DB::table('user_activity_logs')->insert([
                'user_id' => $verification->user_id,
                'action' => 'kyc_rejected',
                'details' => json_encode([
                    'rejected_by' => Auth::id(),
                    'rejected_at' => now(),
                    'reason' => $request->reason,
                    'verification_id' => $id,
                ]),
                'created_at' => now(),
            ]);

            DB::commit();

            return back()->with('success', 'Vérification KYC rejetée avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Erreur lors du rejet: ' . $e->getMessage()]);
        }
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'verification_ids' => 'required|array',
            'verification_ids.*' => 'exists:kyc_verifications,id',
        ]);

        DB::beginTransaction();

        try {
            $approvedCount = 0;

            foreach ($request->verification_ids as $verificationId) {
                $verification = DB::table('kyc_verifications')->where('id', $verificationId)->first();

                if ($verification && $verification->verification_status === 'PENDING') {
                    // Update verification status
                    DB::table('kyc_verifications')
                        ->where('id', $verificationId)
                        ->update([
                            'verification_status' => 'APPROVED',
                            'verified_at' => now(),
                            'updated_at' => now(),
                        ]);

                    // Update artisan profile if applicable
                    $user = DB::table('users')->where('id', $verification->user_id)->first();
                    if ($user && $user->user_type === 'ARTISAN') {
                        DB::table('artisan_profiles')
                            ->where('user_id', $verification->user_id)
                            ->update(['is_kyc_verified' => true]);
                    }

                    // Log the approval
                    DB::table('user_activity_logs')->insert([
                        'user_id' => $verification->user_id,
                        'action' => 'kyc_approved',
                        'details' => json_encode([
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                            'verification_id' => $verificationId,
                            'bulk_approval' => true,
                        ]),
                        'created_at' => now(),
                    ]);

                    $approvedCount++;
                }
            }

            DB::commit();

            return back()->with('success', "{$approvedCount} vérifications KYC approuvées avec succès.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Erreur lors de l\'approbation en masse: ' . $e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        $query = DB::table('kyc_verifications')
            ->select([
                'kyc_verifications.*',
                'users.email',
                'users.user_type',
                'artisan_profiles.trade_category',
            ])
            ->join('users', 'kyc_verifications.user_id', '=', 'users.id')
            ->leftJoin('artisan_profiles', 'users.id', '=', 'artisan_profiles.user_id');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('kyc_verifications.verification_status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('kyc_verifications.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('kyc_verifications.created_at', '<=', $request->date_to);
        }

        $verifications = $query->orderBy('kyc_verifications.created_at', 'desc')->get();

        // Generate CSV
        $filename = 'kyc_verifications_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($verifications) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID Vérification',
                'Email Utilisateur',
                'Type Utilisateur',
                'Catégorie Métier',
                'Type Pièce ID',
                'Numéro Pièce ID',
                'Statut Vérification',
                'Date Soumission',
                'Date Vérification',
                'Raison Rejet',
            ]);

            // CSV data
            foreach ($verifications as $verification) {
                fputcsv($file, [
                    $verification->id,
                    $verification->email,
                    $verification->user_type,
                    $verification->trade_category,
                    $verification->id_type,
                    $verification->id_number,
                    $verification->verification_status,
                    $verification->created_at,
                    $verification->verified_at,
                    $verification->rejection_reason,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getKYCStats()
    {
        return [
            'total_verifications' => DB::table('kyc_verifications')->count(),
            'pending_verifications' => DB::table('kyc_verifications')->where('verification_status', 'PENDING')->count(),
            'approved_verifications' => DB::table('kyc_verifications')->where('verification_status', 'APPROVED')->count(),
            'rejected_verifications' => DB::table('kyc_verifications')->where('verification_status', 'REJECTED')->count(),
            'approval_rate' => $this->calculateApprovalRate(),
            'average_processing_time' => $this->calculateAverageProcessingTime(),
        ];
    }

    private function calculateApprovalRate()
    {
        $total = DB::table('kyc_verifications')->whereIn('verification_status', ['APPROVED', 'REJECTED'])->count();
        $approved = DB::table('kyc_verifications')->where('verification_status', 'APPROVED')->count();

        return $total > 0 ? ($approved / $total) * 100 : 0;
    }

    private function calculateAverageProcessingTime()
    {
        // This would need to be calculated based on submission and verification dates
        // For now, return a placeholder
        return 2.5; // 2.5 days average
    }

    private function getVerificationStatuses()
    {
        return [
            ['value' => 'PENDING', 'label' => 'En attente'],
            ['value' => 'APPROVED', 'label' => 'Approuvé'],
            ['value' => 'REJECTED', 'label' => 'Rejeté'],
        ];
    }

    private function getIdTypes()
    {
        return [
            ['value' => 'CNI', 'label' => 'Carte Nationale d\'Identité'],
            ['value' => 'PASSPORT', 'label' => 'Passeport'],
            ['value' => 'DRIVING_LICENSE', 'label' => 'Permis de Conduire'],
        ];
    }
}
