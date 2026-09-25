<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SupplierCashout;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\GeneratedDocumentService;
use App\Services\SupplierCashoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCashoutController extends Controller
{
    public function __construct(
        private AdminActivityLogger $audit,
        private SupplierCashoutService $cashoutService,
        private GeneratedDocumentService $documentService
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
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'montant_brut' => 'required|integer|min:1000',
            'mode_retrait' => 'required|in:especes_guichet,wave,orange_money,virement_bancaire',
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

        $this->audit->log('cashout.created', $cashout, [
            'supplier_id' => $supplier->id,
            'montant_brut' => $montantBrut,
        ], $cashout->reference, $request->user());

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

        $this->audit->log('cashout.approved', $cashout, [], $cashout->reference, $request->user());

        return back()->with('success', "Demande de retrait {$cashout->reference} approuvée.");
    }

    /**
     * Marque le retrait comme décaissé et complété.
     */
    public function complete(Request $request, SupplierCashout $cashout): RedirectResponse
    {
        if (! in_array($cashout->statut, ['en_attente', 'approuve'])) {
            return back()->with('error', 'Cette demande ne peut pas être complétée.');
        }

        try {
            $refExterne = $request->input('reference_externe');
            $this->cashoutService->completeCashout($cashout, $request->user(), $refExterne);

            return back()->with('success', "Retrait {$cashout->reference} complété. Montant net décaissé et document archivé.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors du décaissement : '.$e->getMessage());
        }
    }

    /**
     * Rejette une demande de retrait.
     */
    public function reject(Request $request, SupplierCashout $cashout): RedirectResponse
    {
        if ($cashout->statut === SupplierCashout::STATUT_COMPLETE) {
            return back()->with('error', 'Une opération déjà complétée ne peut pas être rejetée.');
        }

        $reason = $request->input('reason', 'Demande non conforme');
        $this->cashoutService->rejectCashout($cashout, $request->user(), $reason);

        return back()->with('success', "Demande de retrait {$cashout->reference} rejetée.");
    }

    /**
     * Regroupe des cashouts approuvés dans un bordereau/lot de virement unique.
     */
    public function createBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cashout_ids' => 'required|array|min:1',
            'cashout_ids.*' => 'exists:supplier_cashouts,id',
        ]);

        try {
            $batch = $this->cashoutService->createBatch($validated['cashout_ids'], $request->user());

            return back()->with('success', "Lot {$batch['batch_reference']} créé avec {$batch['count']} retraits pour un net total de {$batch['total_net']} FCFA.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la création du lot : '.$e->getMessage());
        }
    }

    /**
     * Rapprochement bancaire groupé pour un lot de virement.
     */
    public function reconcileBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_reference' => 'required|string',
            'bank_tx_reference' => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->cashoutService->reconcileBatch(
                $validated['batch_reference'],
                $request->user(),
                $validated['bank_tx_reference'] ?? null
            );

            return back()->with('success', "Rapprochement bancaire effectué : {$result['reconciled_count']} retraits réconciliés ({$result['total_reconciled_net']} FCFA).");
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors du rapprochement : '.$e->getMessage());
        }
    }

    /**
     * Exporte un bordereau de virement en CSV.
     */
    public function exportBatchCsv(Request $request, string $batchReference): StreamedResponse
    {
        $cashouts = SupplierCashout::where('batch_reference', $batchReference)
            ->with(['supplier'])
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"Bordereau_Virements_{$batchReference}.csv\"",
        ];

        return response()->stream(function () use ($cashouts) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($handle, [
                'Reference',
                'Quincaillerie',
                'Beneficiaire',
                'Telephone',
                'Mode_Retrait',
                'Banque',
                'RIB_Compte',
                'Montant_Brut_FCFA',
                'Commission_FCFA',
                'Montant_Net_FCFA',
                'Statut',
                'Date_Creation',
            ], ';');

            foreach ($cashouts as $c) {
                fputcsv($handle, [
                    $c->reference,
                    $c->supplier?->name ?? 'Quincaillerie',
                    $c->beneficiary_name,
                    $c->beneficiary_phone,
                    $c->mode_retrait,
                    $c->bank_name ?? 'N/A',
                    $c->bank_account_number ?? 'N/A',
                    $c->montant_brut,
                    $c->montant_commission,
                    $c->montant_net,
                    $c->statut,
                    $c->created_at->format('Y-m-d H:i:s'),
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Télécharge le reçu PDF certifié pour l'administrateur.
     */
    public function receipt(SupplierCashout $cashout): BinaryFileResponse|RedirectResponse
    {
        try {
            $doc = $this->documentService->createOrGetForCashout($cashout);
            $pdfPath = $this->documentService->getPdfPath($doc);

            return response()->download($pdfPath, "Bordereau_Retrait_{$cashout->reference}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Impossible de générer le reçu PDF : '.$e->getMessage());
        }
    }
}
