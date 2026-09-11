<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Services\ScoreService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFraudController extends Controller
{
    public function __construct(
        private ScoreService $scoreService,
        private WalletService $walletService
    ) {}

    /**
     * Active le gel préventif des paiements sur une alerte suspecte.
     */
    public function hold(Request $request, FraudAlert $fraudAlert): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $fraudAlert->update([
            'action_taken' => 'payment_hold',
            'statut' => 'en_analyse',
            'resolution_notes' => $validated['notes'] ?? $fraudAlert->resolution_notes,
        ]);

        Log::warning("[ADMIN FRAUD HOLD] Alerte #{$fraudAlert->reference} mise en gel par admin #" . auth()->id());

        return back()->with('success', "Gel conservatoire des fonds activé pour l'alerte #{$fraudAlert->reference}.");
    }

    /**
     * Lève le gel conservatoire et autorise le déblocage des fonds.
     */
    public function release(Request $request, FraudAlert $fraudAlert): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($fraudAlert, $validated) {
            $fraudAlert->update([
                'action_taken' => 'none',
                'statut' => 'classee',
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'resolution_notes' => $validated['notes'] ?? 'Fonds libérés après vérification administrative.',
            ]);

            // Si un jalon était suspendu sur cette mission, on peut procéder à sa libération
            if ($fraudAlert->mission_id) {
                $jalonsSuspendus = Jalon::where('mission_id', $fraudAlert->mission_id)
                    ->where('statut', 'valide_suspendu')
                    ->get();

                foreach ($jalonsSuspendus as $jalon) {
                    $jalon->update(['statut' => 'valide']);
                    $this->walletService->releaseJalon($jalon);
                    Log::info("[ADMIN FRAUD RELEASE] Jalon #{$jalon->id} libéré suite à la levée d'alerte #{$fraudAlert->reference}");
                }
            }
        });

        return back()->with('success', "Gel levé avec succès. Les fonds associés à l'alerte #{$fraudAlert->reference} ont été libérés.");
    }

    /**
     * Confirme la fraude avérée et applique les pénalités de score.
     */
    public function confirm(Request $request, FraudAlert $fraudAlert): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($fraudAlert, $validated) {
            $fraudAlert->update([
                'statut' => 'confirmee',
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'resolution_notes' => $validated['notes'],
            ]);

            // Sanction sur le score de l'auteur suspecté (ex. -150 pts fraude)
            $user = $fraudAlert->user;
            if ($user && in_array($user->role, ['artisan', 'fournisseur'])) {
                $this->scoreService->recordEvent(
                    artisan: $user,
                    eventType: 'dispute_fraud',
                    missionId: $fraudAlert->mission_id,
                    description: "Fraude confirmée par arbitrage admin (#{$fraudAlert->reference}) : {$validated['notes']}"
                );
            }
        });

        return back()->with('success', "Fraude confirmée pour l'alerte #{$fraudAlert->reference}. Sanctions appliquées au profil.");
    }

    /**
     * Classe l'alerte sans suite (faux positif).
     */
    public function dismiss(Request $request, FraudAlert $fraudAlert): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $fraudAlert->update([
            'statut' => 'rejetee',
            'action_taken' => 'none',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_notes' => $validated['notes'] ?? 'Faux positif classé sans suite.',
        ]);

        return back()->with('success', "Alerte #{$fraudAlert->reference} classée sans suite (faux positif).");
    }
}
