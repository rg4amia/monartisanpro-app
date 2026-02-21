<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ArtisanProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register a new user with multi-role support.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['client', 'artisan', 'fournisseur'])],

            // Artisan-specific fields
            'trade_id' => ['required_if:role,artisan', 'exists:trades,id'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'kyc_status' => 'pending',
                'status' => 'active',
            ]);

            // Create artisan profile if role is artisan
            if ($request->role === 'artisan') {
                // Note: Location will be set later via profile update
                // For now, we'll create a placeholder location
                ArtisanProfile::create([
                    'user_id' => $user->id,
                    'trade_id' => $request->trade_id,
                    'experience_years' => $request->experience_years ?? 0,
                    'bio' => $request->bio,
                    'location' => \MatanYadaev\EloquentSpatial\Objects\Point::make(0, 0), // Placeholder
                    'available' => true,
                ]);
            }

            // Generate Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $user->load('artisanProfile.trade'),
                    'token' => $token,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user and generate token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', Rule::in(['client', 'artisan', 'fournisseur'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides'
            ], 401);
        }

        // Check role mismatch when role is provided
        if ($request->filled('role') && $user->role !== $request->role) {
            return response()->json([
                'success' => false,
                'message' => "Ce compte n'est pas un compte {$request->role}.",
            ], 422);
        }

        // Check if user is banned
        if ($user->status === 'banned') {
            return response()->json([
                'success' => false,
                'message' => 'Account has been banned. Please contact support.'
            ], 403);
        }

        // Check if user is suspended
        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Account is temporarily suspended. Please contact support.'
            ], 403);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('artisanProfile.trade'),
                'token' => $token,
            ]
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Get authenticated user details.
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('artisanProfile.trade', 'kycDocuments')
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'avatar' => ['sometimes', 'image', 'max:5120'], // 5MB max

            // Artisan-specific fields
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'zone_name' => ['sometimes', 'string', 'max:100'],
            'bio' => ['sometimes', 'string', 'max:1000'],
            'experience_years' => ['sometimes', 'integer', 'min:0'],
            'available' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update user fields
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar = $path;
            }

            $user->save();

            // Update artisan profile if user is artisan
            if ($user->isArtisan() && $user->artisanProfile) {
                $profile = $user->artisanProfile;

                if ($request->has('latitude') && $request->has('longitude')) {
                    $profile->location = \MatanYadaev\EloquentSpatial\Objects\Point::make(
                        $request->latitude,
                        $request->longitude
                    );
                }

                if ($request->has('zone_name')) {
                    $profile->zone_name = $request->zone_name;
                }

                if ($request->has('bio')) {
                    $profile->bio = $request->bio;
                }

                if ($request->has('experience_years')) {
                    $profile->experience_years = $request->experience_years;
                }

                if ($request->has('available')) {
                    $profile->available = $request->available;
                }

                $profile->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->load('artisanProfile.trade')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send OTP to user's phone for verification.
     */
    public function sendPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate 6-digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store OTP in cache for 10 minutes
            $cacheKey = 'otp_' . $request->phone;
            Cache::put($cacheKey, $otp, now()->addMinutes(10));

            // TODO: Send SMS via Africa's Talking or Twilio
            // For now, we'll log it (remove in production)
            logger()->info("OTP for {$request->phone}: {$otp}");

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                // TODO: Remove in production
                'debug_otp' => config('app.debug') ? $otp : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify phone OTP.
     */
    public function verifyPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $cacheKey = 'otp_' . $request->phone;
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired or not found'
            ], 400);
        }

        if ($storedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        // OTP is valid, clear it from cache
        Cache::forget($cacheKey);

        // Update user's phone_verified_at if authenticated
        if ($request->user()) {
            $request->user()->update([
                'phone_verified_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully'
        ]);
    }
}
