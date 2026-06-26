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

    /**
     * Initie la réassociation de numéro pour un utilisateur ayant perdu sa carte SIM.
     */
    public function requestResetPhoneLost(Request $request): JsonResponse
    {
        $request->validate([
            'old_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'new_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'name'      => ['required', 'string', 'max:255'],
            'role'      => ['required', 'string', 'in:client,artisan,fournisseur,driver'],
        ]);

        // Vérifie si un autre utilisateur utilise déjà le nouveau numéro
        $alreadyUsed = \App\Models\User::where('phone', $request->new_phone)->exists();
        if ($alreadyUsed) {
            return response()->json([
                'success' => false,
                'message' => 'Le nouveau numéro de téléphone est déjà associé à un compte.',
            ], 422);
        }

        // Trouve l'utilisateur par l'ancien numéro, le rôle et le nom complet exact
        $user = \App\Models\User::where('phone', $request->old_phone)
            ->where('role', $request->role)
            ->where('name', trim($request->name))
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Les informations fournies (ancien numéro, nom complet ou rôle) ne correspondent à aucun compte.',
            ], 404);
        }

        // Envoie l'OTP au nouveau numéro
        $this->otpService->sendOtp($request->new_phone);

        return response()->json([
            'success' => true,
            'message' => 'Code OTP de validation envoyé sur votre nouveau numéro.',
            'expires_in' => $this->otpService->ttlSeconds(),
        ]);
    }

    /**
     * Confirme la réassociation de numéro et connecte l'utilisateur.
     */
    public function confirmResetPhoneLost(Request $request): JsonResponse
    {
        $request->validate([
            'old_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'new_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'name'      => ['required', 'string', 'max:255'],
            'role'      => ['required', 'string', 'in:client,artisan,fournisseur,driver'],
            'otp'       => ['required', 'string', 'size:4'],
        ]);

        // Vérifie l'OTP sur le nouveau numéro
        if (!$this->otpService->verifyOtp($request->new_phone, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP de validation invalide ou expiré.',
            ], 422);
        }

        // Trouve à nouveau l'utilisateur pour modification
        $user = \App\Models\User::where('phone', $request->old_phone)
            ->where('role', $request->role)
            ->where('name', trim($request->name))
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Le compte à réinitialiser est introuvable.',
            ], 404);
        }

        // Met à jour le numéro de téléphone
        $user->update(['phone' => $request->new_phone]);

        // Génère le token Sanctum
        $token = $this->authService->createToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Votre compte a été récupéré avec succès. Votre numéro a été mis à jour.',
            'token'   => $token,
            'user'    => new UserResource($user->load('artisanProfile.sector', 'artisanProfile.trade')),
        ]);
    }

    /**
     * Permet à un utilisateur connecté de modifier son numéro de téléphone.
     */
    public function changePhoneConnected(Request $request): JsonResponse
    {
        $request->validate([
            'new_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'otp'       => ['nullable', 'string', 'size:4'],
        ]);

        $user = $request->user();

        if (!$request->has('otp')) {
            // Envoyer OTP pour validation du nouveau numéro
            $this->otpService->sendOtp($request->new_phone);

            return response()->json([
                'success' => true,
                'message' => 'Code OTP envoyé par SMS sur votre nouveau numéro.',
                'expires_in' => $this->otpService->ttlSeconds(),
            ]);
        }

        // Valider l'OTP
        if (!$this->otpService->verifyOtp($request->new_phone, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide ou expiré.',
            ], 422);
        }

        // Vérifie si le numéro est déjà pris
        if (\App\Models\User::where('phone', $request->new_phone)->where('id', '!=', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce numéro de téléphone est déjà associé à un autre compte.',
            ], 422);
        }

        // Met à jour le numéro de l'utilisateur
        $user->update(['phone' => $request->new_phone]);

        return response()->json([
            'success' => true,
            'message' => 'Votre numéro de téléphone a été modifié avec succès.',
            'user'    => new UserResource($user),
        ]);
    }
}
