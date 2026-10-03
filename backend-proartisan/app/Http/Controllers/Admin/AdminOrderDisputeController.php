<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderDisputeDebt;
use App\Services\OrderDisputeDebtService;
use App\Services\OrderDisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Backoffice — clôture d'un litige de commande, avec son issue (Chantier 21).
 */
class AdminOrderDisputeController extends Controller
{
    public function __construct(private OrderDisputeService $disputes) {}

    public function resolve(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(OrderDispute::OUTCOMES)],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
            // Réclamation acceptée : montant rendu au client et responsable désigné.
            'refund_amount' => ['required_if:outcome,reclamation_acceptee', 'nullable', 'integer', 'min:1'],
            'responsible' => ['required_if:outcome,reclamation_acceptee', 'nullable', Rule::in(OrderDisputeService::RESPONSIBLES)],
        ], [
            'refund_amount.required_if' => 'Indiquez le montant à rembourser au client.',
            'responsible.required_if' => 'Désignez le responsable : fournisseur ou livreur.',
            'note.required' => 'Indiquez le motif de la décision.',
            'note.min' => 'Le motif de la décision doit compter au moins 5 caractères.',
        ]);

        $dispute = $this->disputes->resolve($order, $request->user(), $data['outcome'], $data['note'], [
            'refund_amount' => $data['refund_amount'] ?? null,
            'responsible' => $data['responsible'] ?? null,
        ]);

        $refund = (int) $dispute->refund_amount + (int) $dispute->fare_refund;

        return back()->with('success', "Litige de la commande #{$order->id} clos : {$dispute->outcomeLabel()}."
            .($refund > 0 ? ' Remboursement de '.number_format($refund, 0, ',', ' ').' FCFA engagé.' : ''));
    }

    public function cancelDebt(Request $request, OrderDisputeDebt $debt, OrderDisputeDebtService $debts): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], [
            'reason.required' => "Indiquez le motif de l'annulation.",
            'reason.min' => 'Le motif doit compter au moins 5 caractères.',
        ]);

        $debts->cancel($debt, $request->user(), $data['reason']);

        return back()->with('success', "Dette de litige #{$debt->id} annulée : le compte est débloqué.");
    }
}
