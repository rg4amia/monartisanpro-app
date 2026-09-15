<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Services\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecruitmentController extends Controller
{
    public function __construct(private RecruitmentService $recruitment) {}

    /**
     * Liste des offres actives, filtrable par métier et commune.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trade_id' => 'nullable|integer|exists:trades,id',
            'commune' => 'nullable|string|max:100',
        ]);

        $offers = RecruitmentOffer::query()
            ->active()
            ->with(['trade', 'creator:id,name,role'])
            ->when($validated['trade_id'] ?? null, fn ($q, $tradeId) => $q->where('trade_id', $tradeId))
            ->when($validated['commune'] ?? null, fn ($q, $commune) => $q->where('commune', 'like', "%{$commune}%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $offers]);
    }

    public function show(RecruitmentOffer $offer): JsonResponse
    {
        $offer->load(['trade', 'creator:id,name,role']);

        return response()->json(['success' => true, 'data' => $offer]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trade_id' => 'required|integer|exists:trades,id',
            'title' => 'required|string|max:150',
            'description' => 'required|string',
            'mission_type' => 'required|in:tacheron_brigade,journalier,longue_duree,urgence',
            'commune' => 'required|string|max:100',
            'sous_quartier' => 'nullable|string|max:150',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'date_debut' => 'nullable|date|after_or_equal:today',
            'daily_rate_min' => 'nullable|integer|min:0',
            'daily_rate_max' => 'nullable|integer|min:0|gte:daily_rate_min',
            'openings_count' => 'nullable|integer|min:1|max:100',
            'deadline_at' => 'nullable|date|after:now',
        ]);

        try {
            $offer = $this->recruitment->createOffer($request->user(), $validated);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $offer], 201);
    }

    /**
     * Offres publiées par l'utilisateur connecté (client, fournisseur ou admin).
     */
    public function mine(Request $request): JsonResponse
    {
        $offers = RecruitmentOffer::query()
            ->where('creator_id', $request->user()->id)
            ->with('trade')
            ->withCount('applications')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $offers]);
    }

    /**
     * Candidatures reçues sur une offre appartenant à l'utilisateur connecté.
     * Le numéro de l'artisan n'est jamais exposé (contact via « Demander un
     * rappel ») et la liste reste verrouillée tant que le recruteur n'a pas
     * payé le séquestre d'accès aux candidatures (protection anti-contournement).
     */
    public function applications(Request $request, RecruitmentOffer $offer): JsonResponse
    {
        $user = $request->user();

        if ($offer->creator_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Cette offre ne vous appartient pas.'], 403);
        }

        if ($user->role !== 'admin' && ! $offer->applicantsUnlocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Payez le séquestre d\'accès aux candidatures pour consulter les postulants.',
                'escrow_required' => true,
            ], 402);
        }

        $applications = $offer->applications()
            ->with(['artisan:id,name,score_prosartisan', 'engagement'])
            ->orderByDesc('matching_score')
            ->get();

        return response()->json(['success' => true, 'data' => $applications]);
    }

    /**
     * Demande à l'artisan de rappeler le recruteur — seule façon de
     * l'échanger avec lui, son numéro n'étant jamais transmis directement.
     */
    public function requestCallback(Request $request, RecruitmentApplication $application): JsonResponse
    {
        try {
            $this->recruitment->requestCallback($request->user(), $application);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Demande de rappel envoyée à l\'artisan.']);
    }

    /**
     * Fait évoluer le statut d'une candidature (recruteur propriétaire ou admin).
     */
    public function updateApplicationStatus(Request $request, RecruitmentOffer $offer, RecruitmentApplication $application): JsonResponse
    {
        if ($application->offer_id !== $offer->id) {
            return response()->json(['success' => false, 'message' => "Cette candidature n'appartient pas à cette offre."], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:shortlisted,contacted,rejected,confirmed',
        ]);

        try {
            $application = $this->recruitment->updateApplicationStatus($request->user(), $application, $validated['status']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $application]);
    }

    public function apply(Request $request, RecruitmentOffer $offer): JsonResponse
    {
        try {
            $application = $this->recruitment->applyToOffer($request->user(), $offer);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => $application], 201);
    }

    /**
     * Candidatures de l'artisan connecté, tous statuts confondus.
     */
    public function myApplications(Request $request): JsonResponse
    {
        $applications = RecruitmentApplication::query()
            ->where('artisan_id', $request->user()->id)
            ->with('offer.trade')
            ->orderByDesc('applied_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $applications]);
    }
}
