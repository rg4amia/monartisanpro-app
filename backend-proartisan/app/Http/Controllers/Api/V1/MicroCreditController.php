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

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut consulter son micro-crédit.',
            ], 403);
        }

        $credit = $this->microCreditService->getActiveCredit($user);

        return response()->json([
            'success' => true,
            'data' => $credit ? [
                'id' => $credit->id,
                'amount' => $credit->amount,
                'repaid_amount' => $credit->repaid_amount ?? 0,
                'remaining_amount' => $credit->remaining_amount,
                'status' => $credit->status,
                'approved_at' => $credit->approved_at?->toIso8601String(),
                'disbursed_at' => $credit->disbursed_at?->toIso8601String(),
                'score_prosartisan_at_application' => $credit->score_prosartisan_at_application,
                'external_reference' => $credit->external_reference,
            ] : null,
        ]);
    }

    public function repay(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut effectuer un remboursement.',
            ], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:500'],
            'provider' => ['nullable', 'string', 'in:wave,orange_money'],
        ], [
            'amount.required' => 'Le montant de remboursement est obligatoire.',
            'amount.integer' => 'Le montant doit être un entier.',
            'amount.min' => 'Le montant minimum de remboursement est de 500 FCFA.',
        ]);

        try {
            $result = $this->microCreditService->repayCredit(
                $user,
                (int) $data['amount'],
                $data['provider'] ?? 'wave'
            );

            return response()->json([
                'success' => true,
                'message' => 'Remboursement enregistré avec succès.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function report(Request $request, \App\Services\PdfService $pdfService)
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut télécharger son rapport de solvabilité.',
            ], 403);
        }

        $path = $pdfService->generateSolvabilityReport($user);

        return response()->download($path, 'rapport-solvabilite-prosartisan-'.$user->id.'.pdf');
    }
}
