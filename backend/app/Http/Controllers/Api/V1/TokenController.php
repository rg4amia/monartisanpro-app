<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaterialToken;
use App\Models\TokenRedemption;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use MatanYadaev\EloquentSpatial\Objects\Point;

class TokenController extends Controller
{
    /**
     * Display a listing of tokens (for artisans)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = MaterialToken::with(['project', 'escrowWallet', 'redemptions']);

        // Artisans see their project tokens
        if ($user->role === 'artisan') {
            $query->whereHas('project', function($q) use ($user) {
                $q->where('artisan_id', $user->id);
            });
        }
        // Vendors see tokens they've redeemed
        elseif ($user->role === 'fournisseur') {
            $query->where('vendor_id', $user->id)
                ->orWhereHas('redemptions', function($q) use ($user) {
                    $q->where('vendor_id', $user->id);
                });
        }

        $tokens = $query->orderBy('created_at', 'desc')->get();

        return response()->json($tokens);
    }

    /**
     * Display token details by code
     */
    public function show(string $code)
    {
        $token = MaterialToken::with(['project', 'escrowWallet', 'redemptions.vendor'])
            ->where('code', $code)
            ->firstOrFail();

        return response()->json($token);
    }

    /**
     * Validate token and GPS proximity before redemption
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'vendor_latitude' => 'required|numeric',
            'vendor_longitude' => 'required|numeric',
            'artisan_latitude' => 'required|numeric',
            'artisan_longitude' => 'required|numeric',
        ]);

        $token = MaterialToken::where('code', $validated['code'])->first();

        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        // Check token status
        if ($token->status === 'fully_used') {
            return response()->json(['error' => 'Token already fully used'], 400);
        }

        if ($token->status === 'expired' || $token->expires_at < now()) {
            $token->update(['status' => 'expired']);
            return response()->json(['error' => 'Token expired'], 400);
        }

        // Calculate GPS distance using MySQL ST_Distance_Sphere
        $distance = DB::selectOne("
            SELECT ST_Distance_Sphere(
                POINT(?, ?),
                POINT(?, ?)
            ) as distance
        ", [
            $validated['vendor_longitude'],
            $validated['vendor_latitude'],
            $validated['artisan_longitude'],
            $validated['artisan_latitude']
        ])->distance;

        $maxDistance = config('prosartisan.gps_validation_distance', 100); // 100m default

        return response()->json([
            'valid' => true,
            'token' => $token,
            'distance_meters' => round($distance, 2),
            'max_allowed_meters' => $maxDistance,
            'gps_validation_passed' => $distance <= $maxDistance,
            'validation_method' => $distance <= $maxDistance ? 'gps' : 'otp_sms_required',
        ]);
    }

    /**
     * Redeem token (partial or full)
     */
    public function redeem(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'vendor_latitude' => 'required|numeric',
            'vendor_longitude' => 'required|numeric',
            'artisan_latitude' => 'required|numeric',
            'artisan_longitude' => 'required|numeric',
            'receipt_photo' => 'nullable|image|max:5120', // 5MB
            'validation_method' => 'required|in:gps,otp_sms,admin_override',
            'otp_code' => 'required_if:validation_method,otp_sms',
        ]);

        DB::beginTransaction();
        try {
            $token = MaterialToken::with(['project', 'escrowWallet'])
                ->where('code', $validated['code'])
                ->lockForUpdate()
                ->firstOrFail();

            // Verify token is usable
            if ($token->status === 'fully_used') {
                return response()->json(['error' => 'Token already fully used'], 400);
            }

            if ($token->expires_at < now()) {
                $token->update(['status' => 'expired']);
                return response()->json(['error' => 'Token expired'], 400);
            }

            // Verify amount doesn't exceed remaining value
            if ($validated['amount'] > $token->remaining_value) {
                return response()->json(['error' => 'Amount exceeds remaining token value'], 400);
            }

            // Calculate GPS distance
            $distance = DB::selectOne("
                SELECT ST_Distance_Sphere(
                    POINT(?, ?),
                    POINT(?, ?)
                ) as distance
            ", [
                $validated['vendor_longitude'],
                $validated['vendor_latitude'],
                $validated['artisan_longitude'],
                $validated['artisan_latitude']
            ])->distance;

            $maxDistance = config('prosartisan.gps_validation_distance', 100);

            // Validate based on method
            if ($validated['validation_method'] === 'gps') {
                if ($distance > $maxDistance) {
                    return response()->json([
                        'error' => 'GPS validation failed',
                        'distance_meters' => round($distance, 2),
                        'max_allowed_meters' => $maxDistance,
                        'message' => 'Artisan and vendor must be within 100m to validate with GPS.'
                    ], 403);
                }
            } elseif ($validated['validation_method'] === 'otp_sms') {
                // TODO: Verify OTP code (implement SMS OTP service)
                // For now, assume valid
            }

            // Handle receipt photo upload
            $receiptPath = null;
            if ($request->hasFile('receipt_photo')) {
                $receiptPath = $request->file('receipt_photo')->store('receipts', 'public');
            }

            // Create redemption record
            $redemption = TokenRedemption::create([
                'token_id' => $token->id,
                'vendor_id' => $request->user()->id,
                'artisan_id' => $token->project->artisan_id,
                'amount' => $validated['amount'],
                'vendor_location' => new Point($validated['vendor_latitude'], $validated['vendor_longitude']),
                'artisan_location' => new Point($validated['artisan_latitude'], $validated['artisan_longitude']),
                'distance_meters' => round($distance, 2),
                'validation_method' => $validated['validation_method'],
                'receipt_photo' => $receiptPath,
                'redeemed_at' => now(),
            ]);

            // Update token
            $newRemainingValue = $token->remaining_value - $validated['amount'];
            $token->update([
                'remaining_value' => $newRemainingValue,
                'status' => $newRemainingValue == 0 ? 'fully_used' : 'partially_used',
                'vendor_id' => $request->user()->id, // Track last vendor
            ]);

            // Update escrow wallet
            $token->escrowWallet->increment('material_spent', $validated['amount']);

            // Create transaction record for vendor payment (scheduled J+1)
            Transaction::create([
                'project_id' => $token->project_id,
                'user_id' => $request->user()->id,
                'transaction_ref' => 'TOKEN-' . $token->code . '-' . time(),
                'type' => 'token_redemption',
                'amount' => $validated['amount'],
                'status' => 'pending',
                'metadata' => [
                    'token_id' => $token->id,
                    'redemption_id' => $redemption->id,
                    'scheduled_payout' => now()->addDay()->toDateTimeString(),
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'redemption' => $redemption->load('token'),
                'message' => 'Token redeemed successfully. Payment will be processed within 24 hours.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Redemption failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get redemption history for a token
     */
    public function redemptions(string $code)
    {
        $token = MaterialToken::where('code', $code)->firstOrFail();

        $redemptions = TokenRedemption::with(['vendor', 'artisan'])
            ->where('token_id', $token->id)
            ->orderBy('redeemed_at', 'desc')
            ->get();

        return response()->json($redemptions);
    }
}
