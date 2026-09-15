<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentWorkday;
use App\Models\Transaction;
use App\Services\OrangeMoneyService;
use App\Services\RecruitmentEngagementService;
use App\Services\WaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Engagement de recrutement — séquestre journalier entre un recruteur
 * (client/fournisseur) et l'artisan retenu sur une offre.
 */
class RecruitmentEngagementController extends Controller
{
    public function __construct(
        private RecruitmentEngagementService $engagements,
        private WaveService $waveService,
        private OrangeMoneyService $orangeMoneyService,
    ) {}

    public function store(Request $request, RecruitmentApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'daily_rate' => 'required|integer|min:1',
            'total_days' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $engagement = $this->engagements->createEngagement(
                $request->user(),
                $application,
                $validated['daily_rate'],
                $validated['total_days'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $engagement], 201);
    }

    public function show(Request $request, RecruitmentEngagement $engagement): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->id, [$engagement->artisan_id, $engagement->recruiter_id], true) && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Cet engagement ne vous appartient pas.'], 403);
        }

        $engagement->load(['workdays', 'artisan:id,name,phone', 'recruiter:id,name,phone', 'offer:id,title']);

        return response()->json(['success' => true, 'data' => $engagement]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $engagements = RecruitmentEngagement::query()
            ->where('artisan_id', $user->id)
            ->orWhere('recruiter_id', $user->id)
            ->with(['workdays', 'artisan:id,name,phone', 'recruiter:id,name,phone', 'offer:id,title'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $engagements]);
    }

    public function accept(Request $request, RecruitmentEngagement $engagement): JsonResponse
    {
        try {
            $engagement = $this->engagements->acceptEngagement($request->user(), $engagement);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $engagement->load('workdays')]);
    }

    public function decline(Request $request, RecruitmentEngagement $engagement): JsonResponse
    {
        try {
            $engagement = $this->engagements->declineEngagement($request->user(), $engagement);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $engagement]);
    }

    public function extend(Request $request, RecruitmentEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'additional_days' => 'required|integer|min:1|max:365',
        ]);

        try {
            $engagement = $this->engagements->extendEngagement($request->user(), $engagement, $validated['additional_days']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $engagement->load('workdays')]);
    }

    public function validateWorkday(Request $request, RecruitmentEngagement $engagement, RecruitmentWorkday $workday): JsonResponse
    {
        if ($workday->engagement_id !== $engagement->id) {
            return response()->json(['success' => false, 'message' => "Cette journée n'appartient pas à cet engagement."], 404);
        }

        try {
            $workday = $this->engagements->validateWorkday($request->user(), $workday);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $workday]);
    }

    public function activate(Request $request, RecruitmentEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        try {
            $engagement = $this->engagements->activateEscrow($request->user(), $engagement, $transaction);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $engagement->load('workdays')]);
    }

    /**
     * Initie le paiement du séquestre (montant des jours pas encore payés :
     * lot initial ou complément suite à une prolongation).
     */
    public function initiatePayment(Request $request, RecruitmentEngagement $engagement): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
        ]);

        $recruiter = $request->user();

        try {
            $this->engagements->assertRecruiterOwnsEngagement($recruiter, $engagement);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $montant = $this->engagements->unpaidAmount($engagement);

        if ($montant <= 0) {
            return response()->json(['success' => false, 'message' => 'Aucun jour en attente de paiement pour cet engagement.'], 422);
        }

        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        if ($montant >= $seuil && in_array($validated['provider'], ['wave', 'orange_money'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Les paiements Mobile Money sont limités à '.number_format($seuil, 0, ',', ' ').' FCFA. Veuillez effectuer un virement bancaire.',
            ], 422);
        }

        $provider = PaymentProvider::from($validated['provider']);
        $phone = (string) ($validated['phone'] ?? $recruiter->phone ?? '');

        try {
            $transaction = Transaction::create([
                'user_id' => $recruiter->id,
                'type' => 'recruitment_escrow',
                'montant' => $montant,
                'wallet_source' => $provider === PaymentProvider::VIREMENT_BANCAIRE ? 'client_bank_'.$recruiter->id : 'client_mobile_money_'.$recruiter->id,
                'wallet_dest' => 'escrow_recruitment_'.$engagement->id,
                'provider' => $provider,
                'statut' => PaymentStatus::EN_ATTENTE,
                'client_phone' => $phone,
                'metadata' => [
                    'recruitment_engagement_id' => $engagement->id,
                    'description' => "Séquestre recrutement #{$engagement->id}",
                ],
            ]);

            if ($provider === PaymentProvider::WAVE) {
                $result = $this->waveService->createCheckout(
                    $montant,
                    $phone,
                    "Séquestre recrutement #{$engagement->id}",
                    ['transaction_id' => $transaction->id, 'recruitment_engagement_id' => $engagement->id],
                );

                $transaction->update([
                    'wave_checkout_id' => $result['checkout_id'],
                    'wave_client_reference' => $result['checkout_id'],
                    'reference_externe' => $result['checkout_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url' => $result['checkout_url'],
                        'wave_launch_url' => $result['wave_launch_url'],
                    ]),
                ]);

                return response()->json(['success' => true, 'message' => 'Paiement Wave initié avec succès', 'data' => [
                    'transaction_id' => $transaction->id,
                    'payment_url' => $result['checkout_url'],
                    'wave_launch_url' => $result['wave_launch_url'],
                    'provider' => 'wave',
                ]]);
            }

            if ($provider === PaymentProvider::ORANGE_MONEY) {
                $result = $this->orangeMoneyService->createPayment(
                    $montant,
                    $phone,
                    "Séquestre recrutement #{$engagement->id}",
                    ['transaction_id' => $transaction->id, 'recruitment_engagement_id' => $engagement->id],
                );

                $transaction->update([
                    'orange_order_id' => $result['order_id'],
                    'orange_payment_token' => $result['payment_token'],
                    'reference_externe' => $result['order_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url' => $result['payment_url'],
                        'order_id' => $result['order_id'],
                    ]),
                ]);

                return response()->json(['success' => true, 'message' => 'Paiement Orange Money initié avec succès', 'data' => [
                    'transaction_id' => $transaction->id,
                    'payment_url' => $result['payment_url'],
                    'order_id' => $result['order_id'],
                    'provider' => 'orange_money',
                ]]);
            }

            $reference = 'REF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $transaction->update([
                'reference_externe' => $reference,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'bank_name' => 'ECOBANK CI',
                    'bank_account_name' => 'PROSARTISAN ESCROW',
                    'bank_iban' => 'CI59 CI05 9012 3456 7890 12',
                    'bank_reference' => $reference,
                ]),
            ]);

            return response()->json(['success' => true, 'message' => 'Paiement par Virement Bancaire initié avec succès', 'data' => [
                'transaction_id' => $transaction->id,
                'provider' => 'virement_bancaire',
                'virement_instructions' => [
                    'bank_name' => 'ECOBANK CI',
                    'account_name' => 'PROSARTISAN ESCROW',
                    'iban' => 'CI59 CI05 9012 3456 7890 12',
                    'reference' => $reference,
                ],
            ]]);
        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement séquestre recrutement', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => "Erreur lors de l'initiation du paiement : ".$e->getMessage()], 500);
        }
    }

    /**
     * Initie le paiement du séquestre d'accès aux candidatures d'une offre :
     * verrou financier anti-contournement, payé avant toute consultation de
     * la liste des postulants.
     */
    public function initiateApplicantsUnlockPayment(Request $request, RecruitmentOffer $offer): JsonResponse
    {
        $validated = $request->validate([
            'daily_rate' => 'required|integer|min:1',
            'total_days' => 'nullable|integer|min:1|max:365',
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
        ]);

        $recruiter = $request->user();

        try {
            $this->engagements->assertRecruiterOwnsOffer($recruiter, $offer);

            if ($offer->applicantsUnlocked()) {
                return response()->json(['success' => false, 'message' => 'Les candidatures de cette offre sont déjà consultables.'], 422);
            }

            $computed = $this->engagements->applicantsUnlockAmount(
                $offer,
                $validated['daily_rate'],
                $validated['total_days'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $montant = $computed['amount'];

        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        if ($montant >= $seuil && in_array($validated['provider'], ['wave', 'orange_money'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Les paiements Mobile Money sont limités à '.number_format($seuil, 0, ',', ' ').' FCFA. Veuillez effectuer un virement bancaire.',
            ], 422);
        }

        $provider = PaymentProvider::from($validated['provider']);
        $phone = (string) ($validated['phone'] ?? $recruiter->phone ?? '');

        try {
            $transaction = Transaction::create([
                'user_id' => $recruiter->id,
                'type' => 'recruitment_offer_escrow',
                'montant' => $montant,
                'wallet_source' => $provider === PaymentProvider::VIREMENT_BANCAIRE ? 'client_bank_'.$recruiter->id : 'client_mobile_money_'.$recruiter->id,
                'wallet_dest' => 'escrow_recruitment_offer_'.$offer->id,
                'provider' => $provider,
                'statut' => PaymentStatus::EN_ATTENTE,
                'client_phone' => $phone,
                'metadata' => [
                    'recruitment_offer_id' => $offer->id,
                    'description' => "Séquestre d'accès aux candidatures — offre #{$offer->id}",
                ],
            ]);

            if ($provider === PaymentProvider::WAVE) {
                $result = $this->waveService->createCheckout(
                    $montant,
                    $phone,
                    "Séquestre d'accès aux candidatures — offre #{$offer->id}",
                    ['transaction_id' => $transaction->id, 'recruitment_offer_id' => $offer->id],
                );

                $transaction->update([
                    'wave_checkout_id' => $result['checkout_id'],
                    'wave_client_reference' => $result['checkout_id'],
                    'reference_externe' => $result['checkout_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url' => $result['checkout_url'],
                        'wave_launch_url' => $result['wave_launch_url'],
                    ]),
                ]);

                return response()->json(['success' => true, 'message' => 'Paiement Wave initié avec succès', 'data' => [
                    'transaction_id' => $transaction->id,
                    'payment_url' => $result['checkout_url'],
                    'wave_launch_url' => $result['wave_launch_url'],
                    'provider' => 'wave',
                ]]);
            }

            if ($provider === PaymentProvider::ORANGE_MONEY) {
                $result = $this->orangeMoneyService->createPayment(
                    $montant,
                    $phone,
                    "Séquestre d'accès aux candidatures — offre #{$offer->id}",
                    ['transaction_id' => $transaction->id, 'recruitment_offer_id' => $offer->id],
                );

                $transaction->update([
                    'orange_order_id' => $result['order_id'],
                    'orange_payment_token' => $result['payment_token'],
                    'reference_externe' => $result['order_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url' => $result['payment_url'],
                        'order_id' => $result['order_id'],
                    ]),
                ]);

                return response()->json(['success' => true, 'message' => 'Paiement Orange Money initié avec succès', 'data' => [
                    'transaction_id' => $transaction->id,
                    'payment_url' => $result['payment_url'],
                    'order_id' => $result['order_id'],
                    'provider' => 'orange_money',
                ]]);
            }

            $reference = 'REF-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $transaction->update([
                'reference_externe' => $reference,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'bank_name' => 'ECOBANK CI',
                    'bank_account_name' => 'PROSARTISAN ESCROW',
                    'bank_iban' => 'CI59 CI05 9012 3456 7890 12',
                    'bank_reference' => $reference,
                ]),
            ]);

            return response()->json(['success' => true, 'message' => 'Paiement par Virement Bancaire initié avec succès', 'data' => [
                'transaction_id' => $transaction->id,
                'provider' => 'virement_bancaire',
                'virement_instructions' => [
                    'bank_name' => 'ECOBANK CI',
                    'account_name' => 'PROSARTISAN ESCROW',
                    'iban' => 'CI59 CI05 9012 3456 7890 12',
                    'reference' => $reference,
                ],
            ]]);
        } catch (\Exception $e) {
            Log::error("Erreur initiation paiement séquestre d'accès aux candidatures", ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => "Erreur lors de l'initiation du paiement : ".$e->getMessage()], 500);
        }
    }

    public function activateApplicantsUnlock(Request $request, RecruitmentOffer $offer): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        try {
            $offer = $this->engagements->activateApplicantsUnlock($request->user(), $offer, $transaction);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $offer]);
    }
}
