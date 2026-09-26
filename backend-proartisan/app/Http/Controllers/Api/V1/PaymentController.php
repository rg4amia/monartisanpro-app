<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Initier un paiement pour une mission (acompte).
     * POST /api/v1/payments/initiate
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mission_id' => 'required|exists:missions,id',
            'devis_id' => 'required|exists:devis,id',
            'montant' => 'required|integer|min:100',
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
            'payment_type' => 'nullable|string|in:total,hybrid',
            'promo_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $mission = Mission::findOrFail($request->mission_id);
            $devis = Devis::where('mission_id', $mission->id)->findOrFail($request->devis_id);

            $result = $this->paymentService->initiateMissionPayment(
                $request->user(),
                $mission,
                $devis,
                (int) $request->montant,
                PaymentProvider::from($request->provider),
                $request->input('payment_type', 'total'),
                $request->promo_code,
                $request->phone,
            );

            return response()->json(['success' => true] + $result);
        } catch (PaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatus());
        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement.
     * GET /api/v1/payments/{transaction}/status
     */
    public function checkStatus(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé',
            ], 403);
        }

        try {
            $transaction = $this->paymentService->refreshStatus($transaction);

            return response()->json([
                'success' => true,
                'data' => $this->paymentService->serializeStatus($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur vérification statut paiement', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
            ], 500);
        }
    }

    /**
     * Obtenir l'historique des paiements d'un utilisateur.
     * GET /api/v1/payments/history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $transactions = Transaction::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit($request->query('limit', 20))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions->map(fn ($tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'montant' => $tx->montant,
                    'provider' => $tx->provider->value,
                    'statut' => $tx->statut->value,
                    'mission_id' => $tx->mission_id,
                    'created_at' => $tx->created_at->toIso8601String(),
                    'paid_at' => $tx->paid_at?->toIso8601String(),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération historique paiements', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
            ], 500);
        }
    }

    /**
     * Initier le paiement pour un jalon individuel (Gestion Hybride).
     * POST /api/v1/payments/jalons/{jalon}/pay
     */
    public function initiateJalonPayment(Request $request, Jalon $jalon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $result = $this->paymentService->initiateJalonPayment(
                $request->user(),
                $jalon,
                PaymentProvider::from($request->provider),
                $request->phone,
            );

            return response()->json(['success' => true] + $result);
        } catch (PaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatus());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Régler la course d'une commande livrée (modèle « à la Yango ») :
     * le montant est celui révélé à la livraison, calculé par le serveur.
     * POST /api/v1/payments/orders/{order}/delivery-fare
     */
    public function initiateDeliveryFarePayment(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $result = $this->paymentService->initiateDeliveryFarePayment(
                $request->user(),
                $order,
                PaymentProvider::from($request->provider),
                $request->phone,
            );

            return response()->json(['success' => true] + $result);
        } catch (PaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatus());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Règle une commande de matériaux en attente de paiement (ou tout son
     * panier multi-quincailleries). Montant fixé par le serveur ; client de
     * la commande uniquement (vérifié par le service).
     */
    public function initiateOrderPayment(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $result = $this->paymentService->initiateOrderPayment(
                $request->user(),
                $order,
                PaymentProvider::from($request->provider),
                $request->phone,
            );

            return response()->json(['success' => true] + $result);
        } catch (PaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatus());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Affiche la page de simulation de paiement.
     */
    public function showMockPay(Request $request): View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $transaction = Transaction::findOrFail($request->query('transaction_id'));

        return view('pay', compact('transaction'));
    }

    /**
     * Valide ou échoue le paiement simulé avec application des protocoles de sécurité.
     *
     * Simulateur de développement uniquement : sans paiement réel à confirmer
     * (Wave/Orange Money ne sont pas appelés), cette route ne doit exister
     * qu'en local/testing — en production, un tiers non authentifié pourrait
     * sinon confirmer n'importe quelle transaction en attente et déclencher
     * une fragmentation de séquestre ou une libération de fonds sans qu'aucun
     * FCFA n'ait réellement circulé.
     */
    public function validateMockPay(Request $request): \Illuminate\Contracts\View\View
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $transaction = Transaction::findOrFail($request->input('transaction_id'));

        if ($request->input('action') === 'confirm') {
            $this->paymentService->confirmSimulatedPayment($transaction);
            $statusStr = 'success';
            $message = 'Paiement confirmé avec succès ! Le séquestre a été sécurisé et la mission est financée.';
        } else {
            $this->paymentService->failSimulatedPayment($transaction);
            $statusStr = 'failed';
            $message = 'Le paiement a été annulé par l\'utilisateur.';
        }

        return view('pay_result', compact('transaction', 'statusStr', 'message'));
    }

    private function validationError(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Données de validation invalides',
            'errors' => $validator->errors(),
        ], 422);
    }
}
