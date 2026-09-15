<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentOffer;
use App\Services\RecruitmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecruitmentAdminController extends Controller
{
    public function __construct(private RecruitmentService $recruitment) {}

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

            foreach ($validated as $key => $value) {
                DB::table('recruitment_settings')->where('key', $key)->update([
                    'value' => $value,
                    'updated_at' => now(),
                ]);
            }

            return back()->with('success', 'Réglages du module recrutement enregistrés.');
        } catch (\Throwable $e) {
            Log::error('Erreur updateRecruitmentSettings: '.$e->getMessage());

            return back()->withErrors(['recruitment_settings' => "Impossible d'enregistrer les réglages : ".$e->getMessage()]);
        }
    }
}
