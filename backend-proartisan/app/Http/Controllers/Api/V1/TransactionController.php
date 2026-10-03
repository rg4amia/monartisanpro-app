<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\OrderService;
use App\Services\TransactionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Une valeur inconnue est refusée (422) plutôt que de renvoyer une liste vide.
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'type' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_]+$/'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;

        $query = Transaction::with(['mission.client'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('mission', function ($mq) use ($user) {
                        $mq->where('client_id', $user->id)->orWhere('artisan_id', $user->id);
                    });
            });

        // Types présents dans l'historique de l'utilisateur, avant tout filtre :
        // l'écran ne propose que des filtres qui renvoient quelque chose.
        $types = (clone $query)->reorder()->distinct()->pluck('type')
            ->map(fn ($value) => ['value' => $value, 'label' => TransactionPresenter::typeLabel((string) $value)])
            ->sortBy('label')
            ->values();

        if ($status) {
            $query->where('statut', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate((int) ($filters['per_page'] ?? 30));

        return response()->json([
            'success' => true,
            'data' => TransactionResource::collection($transactions->items()),
            'meta' => [
                'total' => $transactions->total(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'types' => $types,
            ],
        ]);
    }

    /**
     * Lien signé (15 min) vers le reçu PDF d'une transaction confirmée du
     * titulaire : paiement de commande, de course, acompte, retrait livreur,
     * remboursement, versement… (Chantier 11). Propriété vérifiée (Règle
     * d'or 36) : jamais le reçu d'un autre utilisateur.
     */
    public function receiptLink(Request $request, Transaction $transaction): JsonResponse
    {
        if ((int) $transaction->user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Ce reçu ne vous appartient pas.'], 403);
        }

        if (! $transaction->statut->isSuccessful()) {
            return response()->json(['success' => false, 'message' => 'Le reçu est disponible une fois le paiement confirmé.'], 422);
        }

        $expiresAt = now()->addMinutes(15);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => URL::temporarySignedRoute('receipts.transaction.file', $expiresAt, ['transaction' => $transaction->id]),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        // Gain net attendu des courses en cours et des courses livrées non
        // encore réglées par le client, calculé par le service (Règle d'or 49).
        $escrowLivreur = 0;
        if ($user->isLivreur()) {
            $summary = $this->orderService->driverEarningsSummary($user);
            $escrowLivreur = $summary['pending'] + $summary['awaiting_payment'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'walletMateriaux' => $user->wallet_materiaux,
                'walletMo' => $user->wallet_mo,
                'total' => $user->wallet_materiaux + $user->wallet_mo,
                'wallet_escrow_livreur' => $escrowLivreur,
            ],
        ]);
    }
}
