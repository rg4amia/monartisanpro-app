<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountPhoneService;
use App\Services\AntiBotService;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private AuthService $authService,
        private AntiBotService $antiBotService,
        private AccountPhoneService $accountPhone,
    ) {}

    /**
     * Génère un défi anti-robot chiffré pour sécuriser les formulaires web.
     */
    public function getSecurityChallenge(Request $request): JsonResponse
    {
        $action = (string) $request->query('action', 'send_otp');
        $challenge = $this->antiBotService->generateChallenge($action);

        return response()->json([
            'success' => true,
            'challenge' => $challenge,
            'data' => $challenge,
        ]);
    }

    /**
     * Envoie un OTP par SMS ou par WhatsApp au numéro indiqué.
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        // Vérification anti-robot pour les requêtes contenant un défi ou venant du web
        if ($request->has('bot_trap') || $request->has('website_url') || $request->has('bot_token') || $request->has('_bot_token') || $request->input('client_type') === 'web') {
            $botCheck = $this->antiBotService->check($request, 'send_otp');
            if (! $botCheck['success']) {
                $errorCode = ($botCheck['reason'] ?? '') === 'honeypot' ? 'BOT_DETECTED' : 'BOT_CHALLENGE_FAILED';

                return response()->json([
                    'success' => false,
                    'error_code' => $errorCode,
                    'message' => $botCheck['message'] ?? 'Vérification de sécurité anti-robot requise.',
                ], 422);
            }
        }

        $roleParam = $request->input('role');
        if ($roleParam) {
            $role = strtolower($roleParam);
            if ($role === 'driver') {
                $role = 'livreur';
            }

            $blockStatus = Setting::getValueByKey('block_'.$role, 'none');

            if ($blockStatus !== 'none') {
                $user = User::where('phone', $request->phone)->first();
                // Un compte « coquille » (créé par verify-otp, inscription non
                // terminée) se reconnaît à son nom vide. Son rôle n'est PAS nul
                // en production : `users.role` est un ENUM NOT NULL sans défaut,
                // que MariaDB remplit implicitement avec 'client'.
                $isNewUser = ! $user || $user->name === null;

                $shouldBlock = false;
                if ($blockStatus === 'all') {
                    $shouldBlock = true;
                } elseif ($blockStatus === 'new' && $isNewUser) {
                    $shouldBlock = true;
                } elseif ($blockStatus === 'old' && ! $isNewUser) {
                    $shouldBlock = true;
                }

                if ($shouldBlock) {
                    $msg = Setting::getValueByKey('app_access_disabled_message_'.$role)
                        ?: Setting::getValueByKey('app_access_disabled_message', 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.');

                    return response()->json([
                        'success' => false,
                        'message' => $msg,
                    ], 403);
                }
            }
        }

        $channel = $request->input('channel');
        $this->otpService->sendOtp($request->phone, null, $channel);

        $globalChannel = Setting::getValueByKey('otp_delivery_channel', 'sms');
        $effectiveChannel = $channel ?: $globalChannel;

        $msg = 'Code OTP envoyé.';
        if ($effectiveChannel === 'both') {
            $msg = 'Code OTP envoyé par SMS et WhatsApp.';
        } elseif ($effectiveChannel === 'whatsapp') {
            $msg = 'Code OTP envoyé par WhatsApp.';
        } else {
            $msg = 'Code OTP envoyé par SMS.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
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
            $role = $user->role;
            if ($role === 'driver') {
                $role = 'livreur';
            }

            $blockStatus = Setting::getValueByKey('block_'.$role, 'none');
            $shouldBlock = false;

            if ($blockStatus === 'all' || $blockStatus === 'old') {
                $shouldBlock = true;
            }

            if ($shouldBlock) {
                $msg = Setting::getValueByKey('app_access_disabled_message_'.$role)
                    ?: Setting::getValueByKey('app_access_disabled_message', 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.');

                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            $token = $this->authService->createToken($user, $request->input('device_fingerprint'));

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie.',
                'token' => $token,
                'user' => new UserResource($user->load('artisanProfile.sector', 'artisanProfile.trade')),
                'has_completed_profile' => true,
            ]);
        }

        // Sinon, on demande à l'utilisateur de compléter son profil
        return response()->json([
            'success' => true,
            'message' => 'OTP vérifié. Veuillez compléter votre profil.',
            'user_id' => $user->id,
            'phone' => $user->phone,
            'has_completed_profile' => false,
        ]);
    }

    /**
     * Complète l'inscription (nom + rôle) et retourne un token Sanctum.
     * Ce endpoint finalise l'onboarding après vérification OTP.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (strtolower($data['role']) === 'driver') {
            $data['role'] = 'livreur';
        }
        $role = $data['role'];

        $blockStatus = Setting::getValueByKey('block_'.$role, 'none');
        $shouldBlock = false;

        if ($blockStatus === 'all' || $blockStatus === 'new') {
            $shouldBlock = true;
        }

        if ($shouldBlock) {
            $msg = Setting::getValueByKey('app_access_disabled_message_'.$role)
                ?: Setting::getValueByKey('app_access_disabled_message', 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.');

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 403);
        }

        // Preuve de détention du numéro : un code validé récemment.
        $this->authService->assertPhoneVerified($request->phone);

        $user = $this->authService->findOrCreateByPhone($request->phone);

        // Met à jour le profil utilisateur
        $user = $this->authService->register($user, $data);

        // Génère un token d'authentification
        $token = $this->authService->createToken($user, $request->input('device_fingerprint'));

        $this->authService->consumePhoneVerification($request->phone);

        return response()->json([
            'success' => true,
            'message' => 'Inscription complétée avec succès.',
            'token' => $token,
            'user' => new UserResource($user->load('artisanProfile.sector', 'artisanProfile.trade')),
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
            'data' => new UserResource($user),
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
     * Récupération « carte SIM perdue » : fermée (Chantier 27, lot E).
     *
     * L'ancien numéro, le nom et le rôle suffisaient à faire envoyer le code
     * au numéro de l'appelant, donc à prendre le compte d'autrui. Le
     * changement de numéro passe désormais par le support, qui vérifie
     * l'identité du titulaire. Les routes restent déclarées pour répondre aux
     * versions installées de l'application par un message clair.
     */
    public function requestResetPhoneLost(): JsonResponse
    {
        return $this->phoneRecoveryClosed();
    }

    public function confirmResetPhoneLost(): JsonResponse
    {
        return $this->phoneRecoveryClosed();
    }

    private function phoneRecoveryClosed(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'La récupération de compte se fait auprès du support ProsArtisan, qui vérifie votre identité avant de changer votre numéro. Contactez-le depuis « Aide et support ».',
        ], 410);
    }

    /**
     * Permet à un utilisateur connecté de modifier son numéro de téléphone.
     */
    public function changePhoneConnected(Request $request): JsonResponse
    {
        $request->validate([
            'new_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
            'otp' => ['nullable', 'string', 'size:4'],
        ]);

        $user = $request->user();

        if (! $request->has('otp')) {
            $this->accountPhone->sendCode($user, $request->new_phone);

            return response()->json([
                'success' => true,
                'message' => 'Code OTP envoyé par SMS sur votre nouveau numéro.',
                'expires_in' => $this->otpService->ttlSeconds(),
            ]);
        }

        try {
            $user = $this->accountPhone->change(
                $user,
                $request->new_phone,
                $request->otp,
                $user->currentAccessToken()?->id ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre numéro de téléphone a été modifié avec succès.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Permet à un utilisateur existant d'accepter les CGU.
     */
    public function acceptCgu(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->cgu_accepted_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà accepté les conditions générales d\'utilisation.',
            ], 422);
        }

        $user->update(['cgu_accepted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Conditions Générales d\'Utilisation acceptées avec succès.',
            'user' => new UserResource($user->fresh(['artisanProfile.sector', 'artisanProfile.trade', 'fournisseurAgree'])),
        ]);
    }
}
