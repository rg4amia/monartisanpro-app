<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Exceptions\AccountLockedException;
use App\Domain\Identity\Exceptions\AccountSuspendedException;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Exceptions\OTPGenerationException;
use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\Client\Client;
use App\Domain\Identity\Models\Fournisseur\Fournisseur;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Identity\Models\ValueObjects\HashedPassword;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\AuthenticationService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GenerateOTPRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyOTPRequest;
use App\Http\Resources\User\AuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Authentication Controller
 *
 * Handles user registration, login, and OTP operations
 *
 * Requirements: 1.1, 1.2, 1.3, 1.6
 */
class AuthController extends Controller
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthenticationService $authService
    ) {}

    /**
     * Register a new user
     *
     * POST /api/v1/auth/register
     *
     * Creates a new user account (Client, Artisan, or Fournisseur)
     * Artisans and Fournisseurs require KYC verification before full access
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Create value objects
            $email = Email::fromString($validated['email']);
            $password = HashedPassword::fromPlainText($validated['password']);
            $phoneNumber = PhoneNumber::fromString($validated['phone_number']);
            $userType = UserType::fromString($validated['user_type']);

            // Create user based on type
            $user = match ($validated['user_type']) {
                'ARTISAN' => $this->createArtisan($email, $password, $phoneNumber, $validated),
                'FOURNISSEUR' => $this->createFournisseur($email, $password, $phoneNumber, $validated),
                default => $this->createClient($email, $password, $phoneNumber),
            };

            // Save user
            $this->userRepository->save($user);

            // Generate authentication token
            $authToken = $this->authService->generateToken($user);

            // Return response with user data and token
            return response()->json([
                'message' => 'Inscription réussie',
                'data' => new AuthResource($user, $authToken->getToken()),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
                'status_code' => 400,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Registration error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'REGISTRATION_FAILED',
                'message' => 'Une erreur est survenue lors de l\'inscription',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Login user
     *
     * POST /api/v1/auth/login
     *
     * Authenticates user with email and password
     * Returns JWT token on success
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Create value objects
            $email = Email::fromString($validated['email']);

            // Authenticate user
            $authToken = $this->authService->authenticate($email, $validated['password']);

            // Get user
            $user = $this->userRepository->findByEmail($email);

            if ($user === null) {
                throw new InvalidCredentialsException();
            }

            // Return response with user data and token
            return response()->json([
                'message' => 'Connexion réussie',
                'data' => new AuthResource($user, $authToken->getToken()),
            ], 200);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Email ou mot de passe incorrect',
                'status_code' => 401,
            ], 401);
        } catch (AccountLockedException $e) {
            return response()->json([
                'error' => 'ACCOUNT_LOCKED',
                'message' => 'Compte temporairement verrouillé suite à plusieurs tentatives échouées',
                'locked_until' => $e->getLockedUntil()?->format('Y-m-d\TH:i:s\Z'),
                'status_code' => 403,
            ], 403);
        } catch (AccountSuspendedException $e) {
            return response()->json([
                'error' => 'ACCOUNT_SUSPENDED',
                'message' => 'Votre compte a été suspendu',
                'status_code' => 403,
            ], 403);
        } catch (\Exception $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'LOGIN_FAILED',
                'message' => 'Une erreur est survenue lors de la connexion',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Generate OTP
     *
     * POST /api/v1/auth/otp/generate
     *
     * Generates and sends a 6-digit OTP code via SMS
     *
     * @param GenerateOTPRequest $request
     * @return JsonResponse
     */
    public function generateOTP(GenerateOTPRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Create phone number value object
            $phoneNumber = PhoneNumber::fromString($validated['phone_number']);

            // Generate OTP
            $otp = $this->authService->generateOTP($phoneNumber);

            return response()->json([
                'message' => 'Code OTP envoyé avec succès',
                'data' => [
                    'phone_number' => $phoneNumber->getValue(),
                    'expires_at' => $otp->getExpiresAt()->format('Y-m-d\TH:i:s\Z'),
                ],
            ], 200);
        } catch (OTPGenerationException $e) {
            return response()->json([
                'error' => 'OTP_GENERATION_FAILED',
                'message' => 'Impossiblede générer le code OTP',
                'status_code' => 500,
            ], 500);
        } catch (\Exception $e) {
            Log::error('OTP generation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'OTP_GENERATION_FAILED',
                'message' => 'Une erreur est survenue lors de la génération du code OTP',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Verify OTP
     *
     * POST /api/v1/auth/otp/verify
     *
     * Verifies a 6-digit OTP code
     *
     * @param VerifyOTPRequest $request
     * @return JsonResponse
     */
    public function verifyOTP(VerifyOTPRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Create phone number value object
            $phoneNumber = PhoneNumber::fromString($validated['phone_number']);

            // Verify OTP
            $isValid = $this->authService->verifyOTP($phoneNumber, $validated['code']);

            if (!$isValid) {
                return response()->json([
                    'error' => 'INVALID_OTP',
                    'message' => 'Code OTP invalide ou expiré',
                    'status_code' => 400,
                ], 400);
            }

            return response()->json([
                'message' => 'Code OTP vérifié avec succès',
                'data' => [
                    'verified' => true,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('OTP verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'OTP_VERIFICATION_FAILED',
                'message' => 'Une erreur est survenue lors de la vérification du code OTP',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Create a Client user
     */
    private function createClient(Email $email, HashedPassword $password, PhoneNumber $phoneNumber): Client
    {
        return Client::create(
            email: $email,
            password: $password,
            phoneNumber: $phoneNumber
        );
    }

    /**
     * Create an Artisan user
     */
    private function createArtisan(
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        array $data
    ): Artisan {
        $tradeCategory = TradeCategory::fromString($data['trade_category']);
        $location = new GPS_Coordinates(
            latitude: $data['location']['latitude'],
            longitude: $data['location']['longitude'],
            accuracy: $data['location']['accuracy'] ?? 10.0
        );

        return Artisan::create(
            email: $email,
            password: $password,
            phoneNumber: $phoneNumber,
            tradeCategory: $tradeCategory,
            location: $location
        );
    }

    /**
     * Create a Fournisseur user
     */
    private function createFournisseur(
        Email $email,
        HashedPassword $password,
        PhoneNumber $phoneNumber,
        array $data
    ): Fournisseur {
        $shopLocation = new GPS_Coordinates(
            latitude: $data['shop_location']['latitude'],
            longitude: $data['shop_location']['longitude'],
            accuracy: $data['shop_location']['accuracy'] ?? 10.0
        );

        return Fournisseur::create(
            email: $email,
            password: $password,
            phoneNumber: $phoneNumber,
            businessName: $data['business_name'],
            shopLocation: $shopLocation
        );
    }
}
