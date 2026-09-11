<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Services\GeneratedDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminDocumentController extends Controller
{
    public function __construct(
        private GeneratedDocumentService $documentService
    ) {}

    /**
     * Liste des documents au format JSON pour chargement asynchrone / filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search'    => $request->query('search_doc') ?: $request->query('search'),
            'type'      => $request->query('type_doc') ?: $request->query('type'),
            'date_from' => $request->query('date_from'),
            'date_to'   => $request->query('date_to'),
        ];

        $documents = $this->documentService->listDocuments($filters, (int) $request->query('per_page', 25));
        $stats = $this->documentService->getStats();

        return response()->json([
            'documents' => $documents,
            'stats'     => $stats,
        ]);
    }

    /**
     * Téléchargement sécurisé du PDF d'un document.
     */
    public function download(GeneratedDocument $document): BinaryFileResponse
    {
        $path = $this->documentService->getPdfPath($document);

        $filename = "{$document->reference}.pdf";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Synchronise les documents depuis la base de données.
     */
    public function sync(): RedirectResponse
    {
        $count = $this->documentService->syncAll();

        return back()->with('success', "Synchronisation terminée : {$count} nouvelle(s) pièce(s) archivée(s).");
    }

    /**
     * Génère et télécharge directement le reçu officiel pour une transaction.
     */
    public function receiptForTransaction(Transaction $transaction): BinaryFileResponse
    {
        $doc = $this->documentService->createOrGetForTransaction($transaction);
        $path = $this->documentService->getPdfPath($doc);

        return response()->download($path, "{$doc->reference}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Génère et télécharge directement le bordereau officiel pour un cash-out quincaillerie.
     */
    public function receiptForCashout(SupplierCashout $cashout): BinaryFileResponse
    {
        $doc = $this->documentService->createOrGetForCashout($cashout);
        $path = $this->documentService->getPdfPath($doc);

        return response()->download($path, "{$doc->reference}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
