<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\DeliveryFareCollectionService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Backoffice — encaissements (Chantier 11) : relance manuelle d'une course
 * impayée, levée de la restriction d'un client, confirmation d'un virement
 * bancaire reçu pour une commande de matériaux. Chaque action est auditée
 * (Règle d'or 17).
 */
class AdminCollectionController extends Controller
{
    public function __construct(
        private DeliveryFareCollectionService $collection,
        private PaymentService $payments,
        private AdminActivityLogger $audit,
    ) {}

    public function remindFare(Request $request, Order $order): RedirectResponse
    {
        try {
            $order = $this->collection->remind($order, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Client relancé pour la course de la commande #{$order->id} (rappel {$order->delivery_fare_reminders_count}).");
    }

    public function liftRestriction(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Le motif de la levée est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 5 caractères.',
        ]);

        try {
            $this->collection->liftByAdmin($user, $request->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Restriction de {$user->name} levée.");
    }

    public function confirmBankTransfer(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'bank_reference' => 'required|string|min:3|max:120',
        ], [
            'bank_reference.required' => 'La référence du virement reçu est obligatoire.',
        ]);

        try {
            $transaction = $this->payments->confirmOrderBankTransfer($transaction, $validated['bank_reference']);
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('order.bank_transfer_confirmed', $transaction, [
            'montant' => $transaction->montant,
            'order_ids' => $transaction->metadata['order_ids'] ?? [],
            'bank_reference' => $validated['bank_reference'],
        ], "Transaction #{$transaction->id}", $request->user());

        return back()->with('success', "Virement de la transaction #{$transaction->id} confirmé : la commande est transmise à la quincaillerie.");
    }
}
