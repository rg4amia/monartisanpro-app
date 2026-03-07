<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private AuthService $authService,
    ) {}

    /**
     * Envoie un OTP par SMS au numéro indiqué.
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $this->otpService->sendOtp($request->phone);

        return response()->json([
            'success'    => true,
            'message'    => 'Code OTP envoyé par SMS.',
            'expires_in' => $this->otpService->ttlSeconds(),
        ]);
    }

    /**
     * Vérifie l'OTP et connecte l'utilisateur.
     * Si l'utilisateur a déjà complété son profil, retourne un token directement.
     * Sinon, retourne les infos pour rediriger vers l'écran de complétion de profil.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        if (! $this->otpService->verifyOtp($request->phone, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide ou expiré.',
            ], 422);
        }

        // Trouve ou crée l'utilisateur
        $user = $this->authService->findOrCreateByPhone($request->phone);

        $hasCompletedProfile = $user->name !== null && $user->role !== null;

        // Si le profil est déjà complet, on connecte directement
        if ($hasCompletedProfile) {
            $token = $this->authService->createToken($user);

            return response()->json([
                'success'               => true,
                'message'               => 'Connexion réussie.',
                'token'                 => $token,
                'user'                  => new UserResource($user->load('artisanProfile.sector', 'artisanProfile.trade')),
                'has_completed_profile' => true,
            ]);
        }

        // Sinon, on demande à l'utilisateur de compléter son profil
        return response()->json([
            'success'               => true,
            'message'               => 'OTP vérifié. Veuillez compléter votre profil.',
            'user_id'               => $user->id,
            'phone'                 => $user->phone,
            'has_completed_profile' => false,
        ]);
    }

    /**
     * Complète l'inscription (nom + rôle) et retourne un token Sanctum.
     * Ce endpoint finalise l'onboarding après vérification OTP.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->findOrCreateByPhone($request->phone);

        // Met à jour le profil utilisateur
        $user = $this->authService->register($user, $request->validated());

        // Génère un token d'authentification
        $token = $this->authService->createToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Inscription complétée avec succès.',
            'token'   => $token,
            'user'    => new UserResource($user->load('artisanProfile.sector', 'artisanProfile.trade')),
        ]);
    }

    /**
     * Retourne le profil de l'utilisateur connecté.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('artisanProfile.sector', 'artisanProfile.trade', 'fournisseurAgree');

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
        ]);
    }

    /**
     * Déconnexion : révoque tous les tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
