<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\CreateEvaluationRequest;
use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\Order;
use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function __construct(private ScoreService $scoreService) {}

    public function store(CreateEvaluationRequest $request): JsonResponse
    {
        $user = $request->user();
        $missionId = $request->input('mission_id');
        $orderId = $request->input('order_id');
        $evalue = User::findOrFail($request->evalue_id);

        if ($evalue->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas vous auto-évaluer.',
            ], 422);
        }

        $mission = null;
        $order = null;

        if ($missionId) {
            $mission = Mission::findOrFail($missionId);

            if ((string) $mission->status !== 'completed' && (string) $mission->status !== 'terminee') {
                return response()->json([
                    'success' => false,
                    'message' => 'La mission doit être terminée pour pouvoir évaluer.',
                ], 422);
            }

            // Validation que l'évalué est associé à la mission
            $isValidRecipient = false;
            if ($evalue->id === $mission->artisan_id) {
                $isValidRecipient = true;
            } elseif ($evalue->isFournisseur()) {
                $jcodeIds = $mission->jcodes()->pluck('id');
                $hasServed = $mission->jcodes()->where('fournisseur_id', $evalue->id)->exists()
                    || \App\Models\JCodeItem::whereIn('jcode_id', $jcodeIds)->where('served_by_supplier_id', $evalue->id)->exists();
                if ($hasServed) {
                    $isValidRecipient = true;
                }
            } elseif ($evalue->isLivreur()) {
                $isValidRecipient = true;
            }

            if (!$isValidRecipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur évalué n\'est pas associé à cette mission.',
                ], 422);
            }

            // Vérifie doublon
            $exists = Evaluation::where('mission_id', $mission->id)
                ->where('evaluateur_id', $user->id)
                ->where('evalue_id', $evalue->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà évalué cette personne pour cette mission.',
                ], 422);
            }
        } elseif ($orderId) {
            $order = Order::findOrFail($orderId);

            if ($order->status !== 'delivered') {
                return response()->json([
                    'success' => false,
                    'message' => 'La commande doit être livrée pour pouvoir évaluer.',
                ], 422);
            }

            if ($order->client_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seul le client ayant passé la commande peut évaluer.',
                ], 403);
            }

            $isValidRecipient = false;
            if ($order->supplier_id === $evalue->id) {
                $isValidRecipient = true;
            } elseif ($order->driver_id === $evalue->id) {
                $isValidRecipient = true;
            }

            if (!$isValidRecipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur évalué n\'est pas associé à cette commande.',
                ], 422);
            }

            // Vérifie doublon
            $exists = Evaluation::where('order_id', $order->id)
                ->where('evaluateur_id', $user->id)
                ->where('evalue_id', $evalue->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà évalué cette personne pour cette commande.',
                ], 422);
            }
        }

        try {
            $evalData = [
                'evaluateur_id' => $user->id,
                'evalue_id'     => $evalue->id,
                'note'          => (int) $request->note,
                'commentaire'   => $request->commentaire,
                'fiabilite'     => $request->fiabilite ? (int) $request->fiabilite : (int) $request->note,
                'integrite'     => $request->integrite ? (int) $request->integrite : (int) $request->note,
                'qualite'       => $request->qualite ? (int) $request->qualite : (int) $request->note,
                'reactivite'    => $request->reactivite ? (int) $request->reactivite : (int) $request->note,
            ];

            if ($mission) {
                $evalData['mission_id'] = $mission->id;
            }
            if ($order) {
                $evalData['order_id'] = $order->id;
            }

            $evaluation = Evaluation::create($evalData);

            // Recalcul Score ProsArtisan
            $newScore = null;
            try {
                if ($evalue->isArtisan()) {
                    $newScore = $this->scoreService->recalculate($evalue);
                } elseif ($evalue->isFournisseur() || $evalue->isLivreur()) {
                    $newScore = $this->scoreService->recalculateLogistic($evalue);
                }
            } catch (\Throwable $scoreEx) {
                \Illuminate\Support\Facades\Log::warning("Score recalculation warning: " . $scoreEx->getMessage());
                $newScore = $evalue->score_prosartisan;
            }

            return response()->json([
                'success' => true,
                'message' => 'Évaluation enregistrée avec succès.',
                'data'    => [
                    'id'               => $evaluation->id,
                    'note'             => $evaluation->note,
                    'scoreProsArtisan' => $newScore ?? null,
                ],
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Erreur lors de l'enregistrement de l'évaluation: " . $e->getMessage(), [
                'user_id' => $user->id,
                'evalue_id' => $evalue->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de votre évaluation : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupère le statut des acteurs à évaluer pour une mission donnée.
     * GET /api/v1/missions/{mission}/evaluations-status
     */
    public function actorsForMission(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $actors = [];

        // 1. Artisan
        if ($mission->artisan_id) {
            $artisan = User::with('artisanProfile.trade')->find($mission->artisan_id);
            if ($artisan) {
                $eval = Evaluation::where('mission_id', $mission->id)
                    ->where('evaluateur_id', $user->id)
                    ->where('evalue_id', $artisan->id)
                    ->first();

                $actors[] = [
                    'id'          => $artisan->id,
                    'name'        => $artisan->name,
                    'role'        => 'artisan',
                    'role_label'  => 'Artisan',
                    'subtitle'    => $artisan->artisanProfile?->trade?->name ?? 'Artisan qualifié',
                    'avatar_url'  => $artisan->avatar_url ?? null,
                    'is_evaluated'=> $eval !== null,
                    'evaluation'  => $eval ? [
                        'note'        => $eval->note,
                        'commentaire' => $eval->commentaire,
                        'fiabilite'   => $eval->fiabilite,
                        'integrite'   => $eval->integrite,
                        'qualite'     => $eval->qualite,
                        'reactivite'  => $eval->reactivite,
                        'created_at'  => $eval->created_at?->toIso8601String(),
                    ] : null,
                ];
            }
        }

        // 2. Fournisseurs associés aux J-Codes ou items
        $jcodeSupplierIds = $mission->jcodes()->whereNotNull('fournisseur_id')->pluck('fournisseur_id')->unique();
        $itemSupplierIds = \App\Models\JCodeItem::whereIn('jcode_id', $mission->jcodes()->pluck('id'))
            ->whereNotNull('served_by_supplier_id')
            ->pluck('served_by_supplier_id')
            ->unique();
        $allSupplierIds = $jcodeSupplierIds->merge($itemSupplierIds)->unique();

        foreach ($allSupplierIds as $supplierId) {
            $supplier = User::with('fournisseurAgree')->find($supplierId);
            if ($supplier) {
                $eval = Evaluation::where('mission_id', $mission->id)
                    ->where('evaluateur_id', $user->id)
                    ->where('evalue_id', $supplier->id)
                    ->first();

                $actors[] = [
                    'id'          => $supplier->id,
                    'name'        => $supplier->fournisseurAgree?->nom_boutique ?? $supplier->name,
                    'role'        => 'fournisseur',
                    'role_label'  => 'Quincaillerie & Fournisseur',
                    'subtitle'    => 'Fournisseur agréé de matériaux',
                    'avatar_url'  => $supplier->avatar_url ?? null,
                    'is_evaluated'=> $eval !== null,
                    'evaluation'  => $eval ? [
                        'note'        => $eval->note,
                        'commentaire' => $eval->commentaire,
                        'fiabilite'   => $eval->fiabilite,
                        'integrite'   => $eval->integrite,
                        'qualite'     => $eval->qualite,
                        'reactivite'  => $eval->reactivite,
                        'created_at'  => $eval->created_at?->toIso8601String(),
                    ] : null,
                ];
            }
        }

        // 3. Livreur si livraison associée
        $driverId = null;
        $order = Order::where('status', 'delivered')->whereHas('items', function ($q) use ($mission) {
            // Check if any order is related or assigned
        })->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'mission_id' => $mission->id,
                'status'     => (string) $mission->status,
                'is_completed'=> in_array((string) $mission->status, ['completed', 'terminee'], true),
                'actors'     => $actors,
            ],
        ]);
    }

    /**
     * Récupère le statut des acteurs à évaluer pour une commande donnée.
     * GET /api/v1/orders/{order}/evaluations-status
     */
    public function actorsForOrder(Order $order, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($order->client_id !== $user->id && $order->supplier_id !== $user->id && $order->driver_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $actors = [];

        // 1. Fournisseur
        if ($order->supplier_id) {
            $supplier = User::with('fournisseurAgree')->find($order->supplier_id);
            if ($supplier) {
                $eval = Evaluation::where('order_id', $order->id)
                    ->where('evaluateur_id', $user->id)
                    ->where('evalue_id', $supplier->id)
                    ->first();

                $actors[] = [
                    'id'          => $supplier->id,
                    'name'        => $supplier->fournisseurAgree?->nom_boutique ?? $supplier->name,
                    'role'        => 'fournisseur',
                    'role_label'  => 'Fournisseur / Boutique',
                    'subtitle'    => 'Quincaillerie agréée',
                    'avatar_url'  => $supplier->avatar_url ?? null,
                    'is_evaluated'=> $eval !== null,
                    'evaluation'  => $eval ? [
                        'note'        => $eval->note,
                        'commentaire' => $eval->commentaire,
                        'created_at'  => $eval->created_at?->toIso8601String(),
                    ] : null,
                ];
            }
        }

        // 2. Livreur
        if ($order->driver_id) {
            $driver = User::find($order->driver_id);
            if ($driver) {
                $eval = Evaluation::where('order_id', $order->id)
                    ->where('evaluateur_id', $user->id)
                    ->where('evalue_id', $driver->id)
                    ->first();

                $actors[] = [
                    'id'          => $driver->id,
                    'name'        => $driver->name,
                    'role'        => 'livreur',
                    'role_label'  => 'Livreur Express',
                    'subtitle'    => 'Transport & logistique',
                    'avatar_url'  => $driver->avatar_url ?? null,
                    'is_evaluated'=> $eval !== null,
                    'evaluation'  => $eval ? [
                        'note'        => $eval->note,
                        'commentaire' => $eval->commentaire,
                        'created_at'  => $eval->created_at?->toIso8601String(),
                    ] : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'order_id'    => $order->id,
                'status'      => $order->status,
                'is_delivered'=> $order->status === 'delivered',
                'actors'      => $actors,
            ],
        ]);
    }

    /**
     * Liste des évaluations données et reçues par l'utilisateur connecté.
     * GET /api/v1/evaluations/my
     */
    public function myEvaluations(Request $request): JsonResponse
    {
        $user = $request->user();

        $given = Evaluation::with(['evalue', 'mission', 'order'])
            ->where('evaluateur_id', $user->id)
            ->latest('id')
            ->get();

        $received = Evaluation::with(['evaluateur', 'mission', 'order'])
            ->where('evalue_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'given'    => $given,
                'received' => $received,
            ],
        ]);
    }
}

