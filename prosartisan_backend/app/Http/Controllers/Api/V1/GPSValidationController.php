<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Shared\Services\GPSUtilityService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GPS Validation Controller
 * Provides endpoints for GPS-based validation with OTP fallback
 * Implements Requirements 10.2, 10.4, 10.5
 */
class GPSValidationController extends Controller
{
    use ApiResponseTrait;

    private GPSUtilityService $gpsUtilityService;

    public function __construct(GPSUtilityService $gpsUtilityService)
    {
        $this->gpsUtilityService = $gpsUtilityService;
    }

    /**
     * Validate proximity between two locations
     * Used for Jeton validation and Jalon proof verification
     *
     * POST /api/v1/gps/validate-proximity
     */
    public function validateProximity(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_latitude' => 'nullable|numeric|between:-90,90',
                'user_longitude' => 'nullable|numeric|between:-180,180',
                'user_accuracy' => 'nullable|numeric|min:0',
                'required_latitude' => 'required|numeric|between:-90,90',
                'required_longitude' => 'required|numeric|between:-180,180',
                'phone_number' => 'required|string',
                'max_distance' => 'required|numeric|min:1',
                'context' => 'required|string|in:jeton_validation,jalon_proof,location_validation',
            ]);

            $phoneNumber = PhoneNumber::fromString($validated['phone_number']);
            $requiredLocation = new GPS_Coordinates(
                $validated['required_latitude'],
                $validated['required_longitude']
            );

            $userLocation = null;
            if (isset($validated['user_latitude']) && isset($validated['user_longitude'])) {
                $userLocation = new GPS_Coordinates(
                    $validated['user_latitude'],
                    $validated['user_longitude'],
                    $validated['user_accuracy'] ?? 10.0
                );
            }

            $result = $this->gpsUtilityService->performLocationValidation(
                $userLocation,
                $requiredLocation,
                $phoneNumber,
                $validated['max_distance'],
                $validated['context']
            );

            if ($result['success']) {
                return $this->successResponse($result, 'Location validation successful');
            }

            // If OTP fallback is required, return appropriate response
            if (isset($result['next_step']) && $result['next_step'] === 'VERIFY_OTP') {
                return $this->successResponse($result, 'OTP sent for location verification');
            }

            return $this->errorResponse($result['reason'], $result['message'], 400);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid input data', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('INTERNAL_ERROR', 'An error occurred during validation', 500);
        }
    }

    /**
     * Verify OTP for GPS fallback
     *
     * POST /api/v1/gps/verify-otp
     */
    public function verifyOTP(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|string',
                'otp_code' => 'required|string|size:6',
                'context' => 'required|string|in:jeton_validation,jalon_proof,location_validation,gps_fallback',
            ]);

            $phoneNumber = PhoneNumber::fromString($validated['phone_number']);

            $result = $this->gpsUtilityService->verifyGPSFallbackOTP(
                $phoneNumber,
                $validated['otp_code'],
                $validated['context']
            );

            if ($result['success']) {
                return $this->successResponse($result, 'OTP verification successful');
            }

            return $this->errorResponse($result['reason'], $result['message'], 400);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid input data', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('INTERNAL_ERROR', 'An error occurred during OTP verification', 500);
        }
    }

    /**
     * Calculate distance between two GPS coordinates
     *
     * POST /api/v1/gps/calculate-distance
     */
    public function calculateDistance(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'from_latitude' => 'required|numeric|between:-90,90',
                'from_longitude' => 'required|numeric|between:-180,180',
                'to_latitude' => 'required|numeric|between:-90,90',
                'to_longitude' => 'required|numeric|between:-180,180',
            ]);

            $fromCoords = new GPS_Coordinates(
                $validated['from_latitude'],
                $validated['from_longitude']
            );

            $toCoords = new GPS_Coordinates(
                $validated['to_latitude'],
                $validated['to_longitude']
            );

            $distance = $this->gpsUtilityService->calculateDistance($fromCoords, $toCoords);

            return $this->successResponse([
                'distance_meters' => $distance,
                'distance_km' => round($distance / 1000, 2),
                'from_coordinates' => $fromCoords->toArray(),
                'to_coordinates' => $toCoords->toArray(),
            ], 'Distance calculated successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid input data', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('INTERNAL_ERROR', 'An error occurred during distance calculation', 500);
        }
    }

    /**
     * Generate OTP for GPS fallback (manual trigger)
     *
     * POST /api/v1/gps/generate-otp
     */
    public function generateOTP(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|string',
                'context' => 'required|string|in:jeton_validation,jalon_proof,location_validation,gps_fallback',
            ]);

            $phoneNumber = PhoneNumber::fromString($validated['phone_number']);

            $result = $this->gpsUtilityService->generateGPSFallbackOTP(
                $phoneNumber,
                $validated['context']
            );

            if ($result['success']) {
                return $this->successResponse($result, 'OTP generated and sent successfully');
            }

            return $this->errorResponse($result['reason'], $result['message'], 400);
        } catch (ValidationException $e) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid input data', 422, $e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('INTERNAL_ERROR', 'An error occurred during OTP generation', 500);
        }
    }
}
