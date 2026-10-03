<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeSheetRequest;
use App\Models\ImportHistory;
use App\Models\ProductionItem;
use App\Models\StagingItem;
use App\Services\Llm\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backoffice — base de connaissances de l'Assistant IA (Chantier 23) :
 * documents importés, fiches à relire, fiches publiées.
 */
class LlmAdminController extends Controller
{
    public function __construct(private KnowledgeBaseService $knowledge) {}

    // ── Documents ────────────────────────────────────────────────────────────

    public function getImports(): JsonResponse
    {
        return response()->json($this->knowledge->imports());
    }

    public function storeImport(Request $request): JsonResponse
    {
        $extensions = implode(',', array_keys(KnowledgeBaseService::DOCUMENT_TYPES));

        $request->validate([
            'document' => ['required', 'file', 'max:'.KnowledgeBaseService::maxDocumentKilobytes(), "extensions:{$extensions}", "mimes:{$extensions}"],
        ], [
            'document.required' => 'Choisissez un document à importer.',
            'document.max' => 'Le document dépasse la taille autorisée ('.round(KnowledgeBaseService::maxDocumentKilobytes() / 1024).' Mo).',
            'document.extensions' => "Format non accepté. Formats acceptés : {$extensions}.",
            'document.mimes' => "Le contenu du fichier ne correspond pas à un format accepté ({$extensions}).",
        ]);

        $import = $this->knowledge->importDocument($request->file('document'), $request->user());

        return response()->json(['status' => 'success', 'id' => $import->id], 201);
    }

    public function generate(Request $request, string $id): JsonResponse
    {
        $import = $this->knowledge->requestGeneration(ImportHistory::findOrFail($id), $request->user());

        return response()->json(['status' => 'success', 'id' => $import->id], 202);
    }

    public function document(string $id): StreamedResponse
    {
        $file = $this->knowledge->documentFile(ImportHistory::findOrFail($id));

        return Storage::disk($file['disk'])->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyImport(Request $request, string $id): JsonResponse
    {
        $this->knowledge->deleteImport(ImportHistory::findOrFail($id), $request->user());

        return response()->json(['status' => 'success', 'deleted' => $id]);
    }

    public function clearImports(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'deleted' => $this->knowledge->clearImports($request->user())]);
    }

    // ── Fiches à relire ──────────────────────────────────────────────────────

    public function getStaging(): JsonResponse
    {
        return response()->json($this->knowledge->staging());
    }

    public function storeStaging(KnowledgeSheetRequest $request): JsonResponse
    {
        $item = $this->knowledge->createManualSheet($request->validated(), $request->user());

        return response()->json(['status' => 'success', 'id' => $item->id], 201);
    }

    public function updateStaging(KnowledgeSheetRequest $request, string $id): JsonResponse
    {
        $item = $this->knowledge->updateSheet(StagingItem::findOrFail($id), $request->validated(), $request->user());

        return response()->json(['status' => 'success', 'updated' => $item->id]);
    }

    public function approveStaging(Request $request, string $id): JsonResponse
    {
        $item = $this->knowledge->approve(StagingItem::findOrFail($id), $request->user());

        return response()->json(['status' => 'success', 'promoted' => $item->id, 'generated_json' => $item->generated_json]);
    }

    public function rejectStaging(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['reviewer_notes' => 'required|string|min:5|max:1000'], [
            'reviewer_notes.required' => 'Indiquez le motif du rejet.',
            'reviewer_notes.min' => 'Le motif du rejet doit compter au moins 5 caractères.',
        ]);

        $this->knowledge->reject(StagingItem::findOrFail($id), $validated['reviewer_notes'], $request->user());

        return response()->json(['status' => 'success', 'rejected' => $id]);
    }

    public function destroyStaging(Request $request, string $id): JsonResponse
    {
        $this->knowledge->deleteSheet(StagingItem::findOrFail($id), $request->user());

        return response()->json(['status' => 'success', 'deleted' => $id]);
    }

    // ── Fiches publiées ──────────────────────────────────────────────────────

    public function getProduction(): JsonResponse
    {
        return response()->json($this->knowledge->production());
    }

    public function withdrawProduction(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|min:5|max:1000'], [
            'reason.required' => 'Indiquez le motif du retrait.',
            'reason.min' => 'Le motif du retrait doit compter au moins 5 caractères.',
        ]);

        $this->knowledge->withdraw(ProductionItem::findOrFail($id), $validated['reason'], $request->user());

        return response()->json(['status' => 'success', 'withdrawn' => $id]);
    }
}
