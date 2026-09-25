<?php

namespace App\Http\Controllers\Api\V1\Supplier;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Models\SupplierCashout;
use App\Services\GeneratedDocumentService;
use App\Services\SupplierCashoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierCashoutController extends Controller
{
    public function __construct(
        private SupplierCashoutService $cashoutService,
        private GeneratedDocumentService $documentService
    ) {}

    /**
     * Liste des demandes de cashout et statistiques de trésorerie pour la quincaillerie connectée.
     */
    public function index(Request $request): JsonResponse
    {
        $supplier = $request->user();

        $stats = $this->cashoutService->getSupplierCashoutStats($supplier);

        $cashouts = SupplierCashout::where('supplier_id', $supplier->id)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'cashouts' => $cashouts,
            ],
        ]);
    }

    /**
     * Soumission d'une nouvelle demande de cash-out par la quincaillerie.
     */
    public function store(Request $request): JsonResponse
    {
        $supplier = $request->user();

        $validated = $request->validate([
            'montant_brut' => 'required|integer|min:1000',
            'mode_retrait' => 'required|in:especes_guichet,wave,orange_money,virement_bancaire',
            'beneficiary_name' => 'nullable|string|max:150',
            'beneficiary_phone' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $cashout = $this->cashoutService->requestCashout($supplier, $validated);

            return response()->json([
                'success' => true,
                'message' => "Demande de retrait {$cashout->reference} enregistrée avec succès.",
                'data' => $cashout,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'enregistrement de votre demande.',
            ], 500);
        }
    }

    /**
     * Télécharge le reçu officiel certifié PDF d'un cash-out.
     */
    public function receipt(SupplierCashout $cashout, Request $request): BinaryFileResponse|JsonResponse
    {
        $supplier = $request->user();

        // Gating IDOR : le fournisseur ne peut télécharger que ses propres reçus
        if ($cashout->supplier_id !== $supplier->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à ce bordereau de retrait.',
            ], 403);
        }

        try {
            $doc = $this->documentService->createOrGetForCashout($cashout);
            $pdfPath = $this->documentService->getPdfPath($doc);

            return response()->download($pdfPath, "Bordereau_Retrait_{$cashout->reference}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de générer le reçu PDF : '.$e->getMessage(),
            ], 500);
        }
    }
}
