<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDispute;
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
        ], [
            'note.required' => 'Indiquez le motif de la décision.',
            'note.min' => 'Le motif de la décision doit compter au moins 5 caractères.',
        ]);

        $dispute = $this->disputes->resolve($order, $request->user(), $data['outcome'], $data['note']);

        return back()->with('success', "Litige de la commande #{$order->id} clos : {$dispute->outcomeLabel()}.");
    }
}
