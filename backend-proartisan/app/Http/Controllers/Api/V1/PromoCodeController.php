<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    /**
     * Vérifier et appliquer un code promo (Mobile & Web).
     * POST /api/v1/promo-codes/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'amount' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Le code promo est requis.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $codeStr = strtoupper(trim($request->input('code')));
        $amount = (int) $request->input('amount', 0);

        $promo = PromoCode::where('code', $codeStr)->first();

        if (! $promo) {
            return response()->json([
                'success' => false,
                'message' => "Le code promo \"{$codeStr}\" est invalide ou inexistant.",
            ], 404);
        }

        try {
            $discount = $promo->calculateDiscount($amount);
            $finalAmount = max(0, $amount - $discount);

            return response()->json([
                'success' => true,
                'message' => "Code promo \"{$promo->code}\" appliqué avec succès !",
                'data' => [
                    'id' => $promo->id,
                    'code' => $promo->code,
                    'description' => $promo->description,
                    'discount_type' => $promo->discount_type,
                    'discount_value' => $promo->discount_value,
                    'discount_amount' => $discount,
                    'original_amount' => $amount,
                    'final_amount' => $finalAmount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
