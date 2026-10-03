<?php

namespace App\Services\Llm;

use App\Jobs\GenerateKnowledgeSheetsJob;
use App\Models\ImportHistory;
use App\Models\LlmAttachment;
use App\Models\ProductionItem;
use App\Models\StagingItem;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\GeminiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Base de connaissances de l'Assistant IA (Chantier 23) : documents de
 * référence importés, fiches rédigées par l'IA ou saisies à la main, relecture
 * par un administrateur, publication et retrait. Seule une fiche approuvée par
 * un administrateur atteint l'Assistant.
 */
class KnowledgeBaseService
{
    /** Extensions acceptées et type transmis à Gemini. */
    public const DOCUMENT_TYPES = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'md' => 'text/plain',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public const DOCUMENT_DIRECTORY = 'llm-documents';

    public function __construct(
        private GeminiService $gemini,
        private KnowledgeVectorIndex $vectors,
        private AdminActivityLogger $audit,
    ) {}

    public static function maxDocumentKilobytes(): int
    {
        return (int) config('prosartisan.llm.max_document_kb', 15360);
    }

    // ── Documents ────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function imports(): Collection
    {
        return ImportHistory::orderBy('imported_at', 'desc')->get()->map(fn (ImportHistory $import) => $this->presentImport($import));
    }

    public function importDocument(UploadedFile $file, User $admin): ImportHistory
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! isset(self::DOCUMENT_TYPES[$extension])) {
            throw ValidationException::withMessages([
                'document' => 'Format non accepté. Formats acceptés : '.implode(', ', array_keys(self::DOCUMENT_TYPES)).'.',
            ]);
        }

        $id = (string) Str::uuid();
        $path = self::DOCUMENT_DIRECTORY."/{$id}.{$extension}";
        Storage::disk('local')->put($path, $file->get());

        $import = DB::transaction(function () use ($file, $admin, $id, $path, $extension) {
            LlmAttachment::create([
                'id' => $id,
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'extension' => $extension,
                'file_link' => '',
                'uploaded_by' => $admin->name,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => self::DOCUMENT_TYPES[$extension],
                'size' => $file->getSize(),
                'created_at' => now(),
            ]);

            return ImportHistory::create([
                'id' => "doc-{$id}",
                'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_size' => $file->getSize(),
                'imported_at' => now()->format('Y-m-d H:i:s'),
                'status' => ImportHistory::STATUS_WAITING,
                'attachment_id' => $id,
                'uploaded_by_id' => $admin->id,
            ]);
        });

        $this->record('llm.document.imported', ['document_id' => $import->id, 'filename' => $import->filename, 'size' => $import->file_size], $import->filename, $admin);

        return $import;
    }

    /**
     * Lance la rédaction des fiches du document. Le travail part après la
     * réponse HTTP : l'écran suit l'avancement par l'état de l'import.
     */
    public function requestGeneration(ImportHistory $import, User $admin): ImportHistory
    {
        if ($import->currentStatus() === ImportHistory::STATUS_RUNNING) {
            throw ValidationException::withMessages(['document' => 'La génération des fiches de ce document est déjà en cours.']);
        }

        if ($this->attachmentOf($import) === null) {
            throw ValidationException::withMessages(['document' => 'Le fichier de ce document est introuvable : importez-le de nouveau.']);
        }

        $import->update(['status' => ImportHistory::STATUS_RUNNING, 'error_message' => null, 'generation_started_at' => now()]);
        GenerateKnowledgeSheetsJob::dispatchAfterResponse($import->id, $admin->id);

        return $import;
    }

    /**
     * Fait rédiger par Gemini les fiches d'un document et les place en
     * relecture. Sans réponse exploitable, l'import passe en échec avec son
     * motif : aucune fiche de substitution n'est créée (Règle d'or 29).
     */
    public function generateSheets(string $importId, int $adminId): void
    {
        $import = ImportHistory::find($importId);
        if ($import === null) {
            return;
        }

        $attachment = $this->attachmentOf($import);
        $disk = Storage::disk($attachment?->disk ?: 'local');

        if ($attachment === null || ! $attachment->path || ! $disk->exists($attachment->path)) {
            $this->fail($import, 'Le fichier de ce document est introuvable : importez-le de nouveau.');

            return;
        }

        $mime = $attachment->mime_type ?: (self::DOCUMENT_TYPES[strtolower((string) $attachment->extension)] ?? null);
        if ($mime === null) {
            $this->fail($import, "Ce format de fichier n'est pas pris en charge.");

            return;
        }

        $sheets = $this->gemini->extractKnowledgeSheets((string) $disk->get($attachment->path), $mime, $import->filename, $adminId);

        if ($sheets === null) {
            $this->fail($import, "L'analyse du document par l'IA n'a pas abouti. Réessayez plus tard.");

            return;
        }

        $created = 0;
        foreach (array_slice($sheets, 0, 5) as $raw) {
            $id = $this->newSheetId();
            $sheet = KnowledgeSheetSchema::normalize($raw, $id);

            if (! KnowledgeSheetSchema::isUsable($sheet)) {
                continue;
            }

            StagingItem::create([
                'id' => $id,
                'raw_pdf_source' => $import->filename,
                'original_extracted_text' => $sheet['norme_origine']['texte_brut'],
                'generated_json' => $sheet,
                'status' => StagingItem::STATUS_PENDING,
                'import_id' => $import->id,
                'origin' => StagingItem::ORIGIN_AI,
                'model_name' => config('services.gemini.model'),
                'created_by_id' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        if ($created === 0) {
            $this->fail($import, "Aucune règle technique exploitable n'a été trouvée dans ce document.");

            return;
        }

        $import->update([
            'status' => ImportHistory::STATUS_DONE,
            'vlm_extracted' => true,
            'llm_downscaled' => true,
            'sheets_count' => $import->sheets_count + $created,
            'error_message' => null,
        ]);

        $this->record('llm.document.generated', ['document_id' => $import->id, 'fiches' => $created], $import->filename, User::find($adminId));
    }

    /**
     * @return array{path: string, name: string, mime: string, disk: string}
     */
    public function documentFile(ImportHistory $import): array
    {
        $attachment = $this->attachmentOf($import);
        $disk = $attachment?->disk ?: 'local';

        if ($attachment === null || ! $attachment->path || ! Storage::disk($disk)->exists($attachment->path)) {
            abort(404, 'Le fichier de ce document est introuvable.');
        }

        return [
            'path' => $attachment->path,
            'name' => $attachment->original_filename,
            'mime' => $attachment->mime_type ?: 'application/octet-stream',
            'disk' => $disk,
        ];
    }

    public function deleteImport(ImportHistory $import, User $admin): void
    {
        if ($import->currentStatus() === ImportHistory::STATUS_RUNNING) {
            throw ValidationException::withMessages(['document' => 'La génération des fiches de ce document est en cours : attendez sa fin.']);
        }

        $this->removeFiles($import);
        $this->record('llm.document.deleted', ['document_id' => $import->id, 'filename' => $import->filename], $import->filename, $admin);
        $import->delete();
    }

    /** Supprime tous les documents importés. Les fiches déjà rédigées sont conservées. */
    public function clearImports(User $admin): int
    {
        $imports = ImportHistory::all();

        foreach ($imports as $import) {
            $this->removeFiles($import);
            $import->delete();
        }

        $this->record('llm.documents.cleared', ['documents' => $imports->count()], 'Documents de la base de connaissances', $admin);

        return $imports->count();
    }

    // ── Fiches en relecture ──────────────────────────────────────────────────

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function staging(): Collection
    {
        return StagingItem::orderBy('created_at', 'desc')->get()->map(fn (StagingItem $item) => $this->presentSheet($item));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManualSheet(array $data, User $admin): StagingItem
    {
        $id = $this->newSheetId();
        $sheet = KnowledgeSheetSchema::normalize($data, $id);

        $item = StagingItem::create([
            'id' => $id,
            'raw_pdf_source' => 'Saisie manuelle',
            'original_extracted_text' => $sheet['norme_origine']['texte_brut'],
            'generated_json' => $sheet,
            'status' => StagingItem::STATUS_PENDING,
            'origin' => StagingItem::ORIGIN_MANUAL,
            'created_by_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->record('llm.sheet.created', ['fiche_id' => $item->id], $sheet['alternative_prosartisan']['titre_vulgarise'], $admin);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSheet(StagingItem $item, array $data, User $admin): StagingItem
    {
        if ($item->status === StagingItem::STATUS_APPROVED) {
            throw ValidationException::withMessages(['fiche' => 'Une fiche publiée ne se modifie pas : retirez-la de la publication, corrigez-la, puis approuvez-la de nouveau.']);
        }

        $sheet = KnowledgeSheetSchema::normalize($data, $item->id);
        $item->update([
            'generated_json' => $sheet,
            'original_extracted_text' => $sheet['norme_origine']['texte_brut'],
            'status' => StagingItem::STATUS_PENDING,
            'updated_at' => now(),
        ]);

        $this->record('llm.sheet.updated', ['fiche_id' => $item->id], $sheet['alternative_prosartisan']['titre_vulgarise'], $admin);

        return $item;
    }

    /**
     * Publie une fiche vers l'Assistant IA. Seule une fiche à relire et
     * complète (titre, méthode, mot-clé) peut être publiée.
     */
    public function approve(StagingItem $item, User $admin): StagingItem
    {
        if ($item->status !== StagingItem::STATUS_PENDING) {
            throw ValidationException::withMessages(['fiche' => 'Seule une fiche à relire peut être approuvée.']);
        }

        $sheet = KnowledgeSheetSchema::normalize($item->generated_json ?? [], $item->id);

        if (! KnowledgeSheetSchema::isUsable($sheet)) {
            throw ValidationException::withMessages(['fiche' => 'Complétez la fiche avant de la publier : titre, méthode et au moins un mot-clé.']);
        }

        DB::transaction(function () use ($item, $sheet, $admin) {
            $item->update([
                'generated_json' => $sheet,
                'status' => StagingItem::STATUS_APPROVED,
                'validated_at' => now(),
                'validated_by_id' => $admin->id,
            ]);

            ProductionItem::updateOrCreate(['id' => $item->id], [
                'generated_json' => $sheet,
                'tags' => implode(',', $sheet['metadata']['tags_pathologies']),
            ]);
        });

        $this->vectors->upsert($item->id, $sheet);
        $this->record('llm.sheet.approved', ['fiche_id' => $item->id, 'origine' => $item->origin], $sheet['alternative_prosartisan']['titre_vulgarise'], $admin);

        return $item;
    }

    public function reject(StagingItem $item, string $note, User $admin): StagingItem
    {
        if ($item->status !== StagingItem::STATUS_PENDING) {
            throw ValidationException::withMessages(['fiche' => 'Seule une fiche à relire peut être rejetée.']);
        }

        $item->update([
            'status' => StagingItem::STATUS_REJECTED,
            'reviewer_notes' => $note,
            'validated_at' => now(),
            'validated_by_id' => $admin->id,
        ]);

        $this->record('llm.sheet.rejected', ['fiche_id' => $item->id, 'motif' => $note], $this->titleOf($item), $admin);

        return $item;
    }

    public function deleteSheet(StagingItem $item, User $admin): void
    {
        if ($item->status === StagingItem::STATUS_APPROVED) {
            throw ValidationException::withMessages(['fiche' => 'Cette fiche est publiée : retirez-la de la publication avant de la supprimer.']);
        }

        $this->record('llm.sheet.deleted', ['fiche_id' => $item->id, 'statut' => $item->status], $this->titleOf($item), $admin);
        $item->delete();
    }

    // ── Fiches publiées ──────────────────────────────────────────────────────

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function production(): Collection
    {
        return ProductionItem::all()->map(fn (ProductionItem $item) => $item->generated_json)->values();
    }

    /** Retire une fiche de l'Assistant IA ; elle retourne en relecture, corrigeable. */
    public function withdraw(ProductionItem $published, string $reason, User $admin): void
    {
        $title = $published->generated_json['alternative_prosartisan']['titre_vulgarise'] ?? $published->id;

        DB::transaction(function () use ($published, $reason) {
            StagingItem::whereKey($published->id)->update([
                'status' => StagingItem::STATUS_WITHDRAWN,
                'reviewer_notes' => $reason,
                'updated_at' => now(),
            ]);
            $published->delete();
        });

        $this->vectors->delete($published->id);
        $this->record('llm.sheet.withdrawn', ['fiche_id' => $published->id, 'motif' => $reason], $title, $admin);
    }

    // ── Outils ───────────────────────────────────────────────────────────────

    /**
     * Ligne d'audit. Documents et fiches ont un identifiant texte, alors que
     * `admin_activity_logs.subject_id` est numérique : l'identifiant va dans le
     * contexte (MariaDB refuserait la ligne, perdue sans bruit).
     *
     * @param  array<string, mixed>  $context
     */
    private function record(string $action, array $context, string $label, ?User $admin): void
    {
        $this->audit->log($action, null, $context, $label, $admin);
    }

    private function newSheetId(): string
    {
        return 'fiche-'.strtolower((string) Str::ulid());
    }

    private function attachmentOf(ImportHistory $import): ?LlmAttachment
    {
        $key = $import->attachmentKey();

        return $key ? LlmAttachment::find($key) : null;
    }

    private function removeFiles(ImportHistory $import): void
    {
        $attachment = $this->attachmentOf($import);
        if ($attachment === null) {
            return;
        }

        if ($attachment->path) {
            Storage::disk($attachment->disk ?: 'local')->delete($attachment->path);
        }
        $attachment->delete();
    }

    private function fail(ImportHistory $import, string $reason): void
    {
        $import->update(['status' => ImportHistory::STATUS_FAILED, 'error_message' => $reason]);
    }

    private function titleOf(StagingItem $item): string
    {
        return (string) ($item->generated_json['alternative_prosartisan']['titre_vulgarise'] ?? $item->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentImport(ImportHistory $import): array
    {
        return [
            'id' => $import->id,
            'filename' => $import->filename,
            'file_size' => (int) $import->file_size,
            'imported_at' => $import->imported_at,
            'status' => $import->currentStatus(),
            'status_label' => $import->statusLabel(),
            'error_message' => $import->generationInterrupted()
                ? 'La génération des fiches a été interrompue. Relancez-la.'
                : $import->error_message,
            'sheets_count' => (int) $import->sheets_count,
            'has_file' => $this->attachmentOf($import)?->path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSheet(StagingItem $item): array
    {
        return [
            'id' => $item->id,
            'source' => $item->raw_pdf_source,
            'status' => $item->status,
            'status_label' => $item->statusLabel(),
            'origin' => $item->origin ?: StagingItem::ORIGIN_MANUAL,
            'origin_label' => $item->origin === StagingItem::ORIGIN_AI ? 'Générée par IA' : 'Saisie manuelle',
            'reviewer_notes' => $item->reviewer_notes,
            'created_at' => $item->created_at,
            'generated_json' => KnowledgeSheetSchema::normalize($item->generated_json ?? [], $item->id),
        ];
    }
}
