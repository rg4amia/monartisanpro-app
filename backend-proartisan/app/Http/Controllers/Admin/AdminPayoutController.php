<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverCashout;
use App\Models\MobileMoneyPayout;
use App\Services\DriverCashoutService;
use App\Services\MobileMoneyPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Backoffice — versements Mobile Money (relance, versement manuel) et
 * retraits des gains livreur (approbation, versement, rejet). Chaque action
 * est auditée par les services (Règle d'or 17).
 */
class AdminPayoutController extends Controller
{
    public function __construct(
        private MobileMoneyPayoutService $payouts,
        private DriverCashoutService $driverCashouts,
    ) {}

    public function retry(Request $request, MobileMoneyPayout $payout): RedirectResponse
    {
        try {
            $payout = $this->payouts->retryByAdmin($payout, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $payout->isPaid()
            ? back()->with('success', "Versement {$payout->reference} effectué.")
            : back()->with('error', "Le versement {$payout->reference} a de nouveau échoué : {$payout->last_error}");
    }

    public function markPaid(Request $request, MobileMoneyPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => 'required|string|min:3|max:120',
            'note' => 'nullable|string|max:500',
        ], [
            'external_reference.required' => 'La référence du versement hors plateforme est obligatoire.',
        ]);

        try {
            $this->payouts->markPaidManually($payout, $request->user(), $validated['external_reference'], $validated['note'] ?? null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Versement {$payout->reference} soldé manuellement.");
    }

    public function approveCashout(Request $request, DriverCashout $cashout): RedirectResponse
    {
        try {
            $this->driverCashouts->approve($cashout, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Retrait {$cashout->reference} approuvé.");
    }

    public function payCashout(Request $request, DriverCashout $cashout): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => 'nullable|string|min:3|max:120',
        ]);

        try {
            $cashout = $this->driverCashouts->pay($cashout, $request->user(), $validated['external_reference'] ?? null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $cashout->statut === DriverCashout::STATUT_COMPLETE
            ? back()->with('success', "Retrait {$cashout->reference} versé.")
            : back()->with('error', "Le virement du retrait {$cashout->reference} a échoué ; il sera relancé automatiquement.");
    }

    public function rejectCashout(Request $request, DriverCashout $cashout): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire.',
            'reason.min' => 'Le motif du rejet doit comporter au moins 5 caractères.',
        ]);

        try {
            $this->driverCashouts->reject($cashout, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Retrait {$cashout->reference} rejeté.");
    }
}
