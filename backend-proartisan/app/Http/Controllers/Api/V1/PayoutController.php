<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileMoneyPayout;
use App\Services\MobileMoneyPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Versements Mobile Money reçus par l'utilisateur connecté (artisan,
 * livreur) : suivi, historique et relance d'un virement échoué.
 */
class PayoutController extends Controller
{
    public function __construct(private MobileMoneyPayoutService $payouts) {}

    /**
     * GET /api/v1/payouts
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->payouts->listFor($request->user()),
        ]);
    }

    /**
     * POST /api/v1/payouts/{payout}/retry — relance par le bénéficiaire
     * (après correction de son numéro de paiement, par exemple).
     */
    public function retry(Request $request, MobileMoneyPayout $payout): JsonResponse
    {
        if ($payout->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Ce versement ne vous appartient pas.'], 403);
        }

        try {
            $payout = $this->payouts->retryByBeneficiary($payout, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $payout->isPaid()
                ? 'Virement effectué avec succès.'
                : 'Le virement a de nouveau échoué. Vérifiez votre numéro de paiement ; une relance automatique est prévue.',
            'data' => $this->payouts->present($payout->load('user')),
        ]);
    }
}
