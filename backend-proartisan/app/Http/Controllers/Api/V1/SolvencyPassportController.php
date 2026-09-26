<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SolvencyPassportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolvencyPassportController extends Controller
{
    public function __construct(private SolvencyPassportService $passportService) {}

    /**
     * Génère ou consulte le passeport de solvabilité d'un artisan.
     */
    public function show(User $artisan, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $user->id !== $artisan->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        try {
            $passport = $this->passportService->generatePassport($artisan);

            return response()->json([
                'success' => true,
                'data' => $passport,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Vérifie l'authenticité d'un passeport scanné via QR code ou jeton public.
     */
    public function verify(Request $request): JsonResponse
    {
        $token = (string) $request->query('token');
        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Jeton de vérification manquant.',
            ], 400);
        }

        $passport = $this->passportService->verifyPassportToken($token);

        if (! $passport) {
            return response()->json([
                'success' => false,
                'message' => 'Passeport de solvabilité invalide ou signature altérée.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Passeport de solvabilité authentique et certifié.',
            'data' => $passport,
        ]);
    }

    /**
     * Calcule la prime de la Garantie Chantier Sérénité.
     */
    public function calculateInsurance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
        ]);

        $quote = $this->passportService->calculateGuaranteeOption((int) $data['amount']);

        return response()->json([
            'success' => true,
            'data' => $quote,
        ]);
    }
}
