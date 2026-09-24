<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentEngagement;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentWorkday;
use App\Models\Transaction;
use App\Services\PaymentService;
use App\Services\RecruitmentEngagementService;
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
        private PaymentService $payments,
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

        try {
            $this->engagements->assertRecruiterOwnsEngagement($request->user(), $engagement);

            $result = $this->payments->initiateRecruitmentEngagementPayment(
                $request->user(),
                $engagement,
                PaymentProvider::from($validated['provider']),
                $validated['phone'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (PaymentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatus());
        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement séquestre recrutement', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => "Erreur lors de l'initiation du paiement : ".$e->getMessage()], 500);
        }

        return response()->json(['success' => true] + $result);
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

            $result = $this->payments->initiateApplicantsUnlockPayment(
                $recruiter,
                $offer,
                $computed['amount'],
                PaymentProvider::from($validated['provider']),
                $validated['phone'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (PaymentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatus());
        } catch (\Exception $e) {
            Log::error("Erreur initiation paiement séquestre d'accès aux candidatures", ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => "Erreur lors de l'initiation du paiement : ".$e->getMessage()], 500);
        }

        return response()->json(['success' => true] + $result);
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
