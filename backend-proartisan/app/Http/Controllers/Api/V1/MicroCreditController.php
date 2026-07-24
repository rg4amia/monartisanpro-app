<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MicroCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MicroCreditController extends Controller
{
    public function __construct(private MicroCreditService $microCreditService) {}

    public function eligibility(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut consulter le micro-crédit.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->microCreditService->checkEligibility($user),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut demander un micro-crédit.',
            ], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
        ], [
            'amount.required' => 'Le montant demandé est obligatoire.',
            'amount.integer' => 'Le montant doit être un entier.',
            'amount.min' => 'Le montant minimum est de 1 000 FCFA.',
        ]);

        try {
            $application = $this->microCreditService->applyForCredit(
                $user,
                (int) $data['amount']
            );

            return response()->json([
                'success' => true,
                'message' => 'Votre demande de micro-crédit a été soumise.',
                'data' => [
                    'id' => $application->id,
                    'amount' => $application->amount,
                    'status' => $application->status,
                    'score_prosartisan_at_application' => $application->score_prosartisan_at_application,
                    'approved_at' => $application->approved_at?->toIso8601String(),
                    'external_reference' => $application->external_reference,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
