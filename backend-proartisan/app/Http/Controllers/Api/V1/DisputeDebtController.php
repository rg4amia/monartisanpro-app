<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\OrderDisputeDebt;
use App\Services\OrderDisputeDebtService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dettes de litige de commande du fournisseur ou du livreur connecté :
 * consultation et règlement direct par Mobile Money (Chantier 22).
 */
class DisputeDebtController extends Controller
{
    public function __construct(
        private OrderDisputeDebtService $debts,
        private PaymentService $payments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->debts->listFor($request->user()),
        ]);
    }

    public function pay(Request $request, OrderDisputeDebt $debt): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:wave,orange_money'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $result = $this->payments->initiateDisputeDebtPayment(
                $request->user(),
                $debt,
                PaymentProvider::from($data['provider']),
                $data['phone'] ?? null,
            );

            return response()->json(['success' => true] + $result);
        } catch (PaymentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatus());
        }
    }
}
