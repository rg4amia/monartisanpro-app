<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Services\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecruitmentAdminController extends Controller
{
    public function __construct(private RecruitmentService $recruitment) {}

    /**
     * Candidatures reçues sur une offre — consultées depuis la modale du
     * tableau de bord recrutement.
     */
    public function applications(RecruitmentOffer $offer): JsonResponse
    {
        $applications = $offer->applications()
            ->with('artisan:id,name,phone,score_prosartisan')
            ->orderByDesc('matching_score')
            ->get();

        return response()->json(['success' => true, 'data' => $applications]);
    }

    public function updateApplicationStatus(Request $request, RecruitmentOffer $offer, RecruitmentApplication $application): RedirectResponse
    {
        if ($application->offer_id !== $offer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'required|in:shortlisted,contacted,rejected,confirmed',
        ]);

        try {
            $this->recruitment->updateApplicationStatus($request->user(), $application, $validated['status']);
        } catch (ValidationException $e) {
            return back()->withErrors(['application' => $e->getMessage()]);
        }

        return back()->with('success', 'Statut de la candidature mis à jour.');
    }

    public function approve(RecruitmentOffer $offer): RedirectResponse
    {
        $this->recruitment->approve($offer);

        return back()->with('success', 'Offre de recrutement approuvée et publiée.');
    }

    public function reject(Request $request, RecruitmentOffer $offer): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->recruitment->reject($offer, $validated['reason'] ?? null);

        return back()->with('success', 'Offre de recrutement rejetée.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'client_posting_enabled' => 'nullable|in:0,1',
                'fournisseur_posting_enabled' => 'nullable|in:0,1',
            ]);

            $this->recruitment->updateSettings($validated);

            return back()->with('success', 'Réglages du module recrutement enregistrés.');
        } catch (\Throwable $e) {
            Log::error('Erreur updateRecruitmentSettings: '.$e->getMessage());

            return back()->withErrors(['recruitment_settings' => "Impossible d'enregistrer les réglages : ".$e->getMessage()]);
        }
    }
}
