<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SupplierCashout;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCashoutController extends Controller
{
    public function __construct(
        private ?AdminActivityLogger $audit = null
    ) {}

    /**
     * Enregistre une opération de cash-out pour une quincaillerie.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:users,id',
            'beneficiary_name' => 'required|string|max:150',
            'beneficiary_phone' => 'required|string|max:20',
            'montant_brut' => 'required|integer|min:1000',
            'mode_retrait' => 'required|in:especes_guichet,wave,orange_money',
            'notes' => 'nullable|string|max:500',
        ]);

        $supplier = User::findOrFail($validated['supplier_id']);
        if ($supplier->role !== 'fournisseur') {
            return back()->with('error', 'L\'utilisateur sélectionné n\'est pas une quincaillerie agréée.');
        }

        $rate = 0.025;
        $setting = Setting::where('key', 'commission_cashout_quincaillerie')->first();
        if ($setting && is_numeric($setting->value)) {
            $rate = (float) $setting->value;
        }

        $montantBrut = (int) $validated['montant_brut'];
        $commission = (int) round($montantBrut * $rate);
        $net = max(0, $montantBrut - $commission);

        $cashout = SupplierCashout::create([
            'reference' => SupplierCashout::generateReference(),
            'supplier_id' => $supplier->id,
            'beneficiary_name' => $validated['beneficiary_name'],
            'beneficiary_phone' => $validated['beneficiary_phone'],
            'montant_brut' => $montantBrut,
            'commission_rate' => $rate,
            'montant_commission' => $commission,
            'montant_net' => $net,
            'statut' => 'en_attente',
            'mode_retrait' => $validated['mode_retrait'],
            'notes' => $validated['notes'] ?? null,
            'processed_by' => $request->user()?->id,
        ]);

        if ($this->audit && $request->user()) {
            $this->audit->log($request->user(), 'cashout.created', [
                'cashout_id' => $cashout->id,
                'reference' => $cashout->reference,
                'supplier_id' => $supplier->id,
                'montant_brut' => $montantBrut,
            ]);
        }

        return back()->with('success', "Opération de retrait {$cashout->reference} enregistrée.");
    }

    /**
     * Approuve une demande de retrait cash.
     */
    public function approve(Request $request, SupplierCashout $cashout): RedirectResponse
    {
        if ($cashout->statut !== 'en_attente') {
            return back()->with('error', 'Seule une demande en attente peut être approuvée.');
        }

        $cashout->update([
            'statut' => 'approuve',
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        if ($this->audit && $request->user()) {
            $this->audit->log($request->user(), 'cashout.approved', [
                'cashout_id' => $cashout->id,
                'reference' => $cashout->reference,
            ]);
        }

        return back()->with('success', "Demande de retrait {$cashout->reference} approuvée.");
    }

    /**
     * Marque le retrait comme décaissé et complété.
     */
    public function complete(Request $request, SupplierCashout $cashout): RedirectResponse
    {
        if (!in_array($cashout->statut, ['en_attente', 'approuve'])) {
            return back()->with('error', 'Cette demande ne peut pas être complétée.');
        }

        $cashout->update([
            'statut' => 'complete',
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        if ($this->audit && $request->user()) {
            $this->audit->log($request->user(), 'cashout.completed', [
                'cashout_id' => $cashout->id,
                'reference' => $cashout->reference,
                'montant_net' => $cashout->montant_net,
            ]);
        }

        return back()->with('success', "Retrait {$cashout->reference} complété. Montant net décaissé.");
    }

    /**
     * Rejette une demande de retrait.
     */
    public function reject(Request $request, SupplierCashout $cashout): RedirectResponse
    {
        if ($cashout->statut === 'complete') {
            return back()->with('error', 'Une opération déjà complétée ne peut pas être rejetée.');
        }

        $reason = $request->input('reason', 'Demande non conforme');
        $notes = $cashout->notes ? "{$cashout->notes}\nRejet : {$reason}" : "Rejet : {$reason}";

        $cashout->update([
            'statut' => 'rejete',
            'notes' => $notes,
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        if ($this->audit && $request->user()) {
            $this->audit->log($request->user(), 'cashout.rejected', [
                'cashout_id' => $cashout->id,
                'reference' => $cashout->reference,
                'reason' => $reason,
            ]);
        }

        return back()->with('success', "Demande de retrait {$cashout->reference} rejetée.");
    }
}
