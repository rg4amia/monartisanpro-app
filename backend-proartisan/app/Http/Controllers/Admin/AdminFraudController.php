<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudAlert;
use App\Services\Admin\FraudAlertAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminFraudController extends Controller
{
    public function __construct(private FraudAlertAdminService $fraudAlerts) {}

    /**
     * Active le gel préventif des paiements sur une alerte suspecte.
     */
    public function hold(Request $request, FraudAlert $fraudAlert): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->fraudAlerts->hold($fraudAlert, $request->user(), $validated['notes'] ?? null);

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

        $result = $this->fraudAlerts->release($fraudAlert, $request->user(), $validated['notes'] ?? null);

        $message = "Gel levé pour l'alerte #{$fraudAlert->reference}.";
        if ($result['paid'] > 0) {
            $message .= " {$result['paid']} jalon(s) payé(s).";
        }
        if ($result['awaiting_referent'] > 0) {
            $message .= " {$result['awaiting_referent']} jalon(s) en attente de la validation physique du Référent (mission au-delà du seuil).";
        }

        return back()->with('success', $message);
    }

    /**
     * Confirme la fraude avérée et applique les pénalités de score.
     */
    public function confirm(Request $request, FraudAlert $fraudAlert): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $this->fraudAlerts->confirm($fraudAlert, $request->user(), $validated['notes']);

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

        $this->fraudAlerts->dismiss($fraudAlert, $request->user(), $validated['notes'] ?? null);

        return back()->with('success', "Alerte #{$fraudAlert->reference} classée sans suite (faux positif).");
    }
}
