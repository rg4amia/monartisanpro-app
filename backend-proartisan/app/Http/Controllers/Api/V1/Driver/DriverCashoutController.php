<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Services\DriverCashoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retrait des gains du livreur (modèle du cash-out quincaillerie).
 */
class DriverCashoutController extends Controller
{
    public function __construct(private DriverCashoutService $cashoutService) {}

    /**
     * GET /api/v1/driver/cashouts — solde retirable, statistiques et demandes.
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $driver->isLivreur()) {
            return response()->json(['success' => false, 'message' => 'Espace réservé aux livreurs.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $this->cashoutService->stats($driver),
                'cashouts' => $this->cashoutService->listFor($driver),
            ],
        ]);
    }

    /**
     * POST /api/v1/driver/cashouts — nouvelle demande de retrait.
     */
    public function store(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! $driver->isLivreur()) {
            return response()->json(['success' => false, 'message' => 'Espace réservé aux livreurs.'], 403);
        }

        $validated = $request->validate([
            'montant_brut' => 'required|integer|min:'.DriverCashoutService::MINIMUM_AMOUNT.'|max:100000000',
            'mode_retrait' => 'required|in:wave,orange_money,virement_bancaire',
            'beneficiary_name' => 'nullable|string|max:150',
            'beneficiary_phone' => ['nullable', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ], [
            'beneficiary_phone.regex' => 'Le numéro doit être au format +225 suivi de 10 chiffres.',
            'montant_brut.min' => 'Le montant minimum de retrait est de '.DriverCashoutService::MINIMUM_AMOUNT.' FCFA.',
        ]);

        try {
            $cashout = $this->cashoutService->requestCashout($driver, $validated);

            return response()->json([
                'success' => true,
                'message' => "Demande de retrait {$cashout->reference} enregistrée. Elle sera traitée par l'équipe ProsArtisan.",
                'data' => $this->cashoutService->present($cashout),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
