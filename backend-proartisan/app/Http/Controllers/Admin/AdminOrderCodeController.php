<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderCodeSuspensionAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Backoffice — levée de la suspension d'un code de retrait ou de réception
 * (Chantier 34).
 */
class AdminOrderCodeController extends Controller
{
    public function __construct(private OrderCodeSuspensionAdminService $suspensions) {}

    public function unlock(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', Rule::in(array_keys(OrderCodeSuspensionAdminService::KINDS))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'code.required' => 'Indiquez le code concerné.',
            'code.in' => 'Code inconnu.',
            'reason.required' => 'Indiquez le motif de la levée.',
            'reason.min' => 'Le motif doit compter au moins 5 caractères.',
        ]);

        try {
            $this->suspensions->lift($order, $data['code'], $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $label = OrderCodeSuspensionAdminService::KINDS[$data['code']];

        return back()->with('success', "Commande #{$order->id} : la saisie du code de {$label} est de nouveau possible.");
    }
}
