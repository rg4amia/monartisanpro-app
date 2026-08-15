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

        if (!$promo) {
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

    /**
     * Liste des codes promo (Backoffice Admin).
     * GET /api/v1/promo-codes
     */
    public function index(): JsonResponse
    {
        $promoCodes = PromoCode::orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $promoCodes,
        ]);
    }

    /**
     * Créer un nouveau code promo (Backoffice Admin).
     * POST /api/v1/promo-codes
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'min_order_amount' => 'nullable|integer|min:0',
            'max_discount_amount' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de code promo invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $promo = PromoCode::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Code promo {$promo->code} créé avec succès.",
            'data' => $promo,
        ], 201);
    }

    /**
     * Modifier un code promo existant.
     * PUT /api/v1/promo-codes/{promoCode}
     */
    public function update(Request $request, PromoCode $promoCode): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $promoCode->id,
            'description' => 'nullable|string|max:255',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'min_order_amount' => 'nullable|integer|min:0',
            'max_discount_amount' => 'nullable|integer|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $request->boolean('is_active', $promoCode->is_active);

        $promoCode->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Code promo {$promoCode->code} mis à jour avec succès.",
            'data' => $promoCode,
        ]);
    }

    /**
     * Supprimer un code promo.
     * DELETE /api/v1/promo-codes/{promoCode}
     */
    public function destroy(PromoCode $promoCode): JsonResponse
    {
        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Code promo supprimé avec succès.',
        ]);
    }

    /**
     * Activer / Désactiver un code promo.
     */
    public function toggle(PromoCode $promoCode): JsonResponse
    {
        $promoCode->update(['is_active' => !$promoCode->is_active]);

        $status = $promoCode->is_active ? 'activé' : 'désactivé';

        return response()->json([
            'success' => true,
            'message' => "Code promo {$promoCode->code} {$status}.",
            'data' => $promoCode,
        ]);
    }
}
