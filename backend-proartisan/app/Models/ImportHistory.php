<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Document de référence importé pour alimenter la base de connaissances.
 */
class ImportHistory extends Model
{
    public const STATUS_WAITING = 'en_attente';

    public const STATUS_RUNNING = 'en_cours';

    public const STATUS_DONE = 'traite';

    public const STATUS_FAILED = 'echec';

    /** Statuts écrits par l'ancien écran, ramenés aux statuts actuels à la lecture. */
    private const LEGACY_STATUSES = [
        'PENDING_VLM' => self::STATUS_WAITING,
        'PENDING_LLM' => self::STATUS_WAITING,
        'INGESTED' => self::STATUS_DONE,
    ];

    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'En attente',
        self::STATUS_RUNNING => 'Génération en cours',
        self::STATUS_DONE => 'Fiches générées',
        self::STATUS_FAILED => 'Échec',
    ];

    protected $table = 'import_history';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'filename',
        'file_size',
        'imported_at',
        'status',
        'vlm_extracted',
        'llm_downscaled',
        'attachment_id',
        'uploaded_by_id',
        'error_message',
        'sheets_count',
        'generation_started_at',
    ];

    protected $casts = [
        'vlm_extracted' => 'boolean',
        'llm_downscaled' => 'boolean',
        'sheets_count' => 'integer',
        'generation_started_at' => 'datetime',
    ];

    /** Au-delà de ce délai, une génération restée « en cours » est tenue pour interrompue. */
    public const GENERATION_TIMEOUT_MINUTES = 10;

    public function currentStatus(): string
    {
        if ($this->generationInterrupted()) {
            return self::STATUS_FAILED;
        }

        return self::LEGACY_STATUSES[$this->status] ?? (string) $this->status;
    }

    /**
     * Le traitement s'exécute après la réponse HTTP : si le processus est
     * coupé, rien ne remet l'état à jour. Le délai rend l'import relançable.
     */
    public function generationInterrupted(): bool
    {
        return $this->status === self::STATUS_RUNNING
            && ($this->generation_started_at === null
                || $this->generation_started_at->lt(now()->subMinutes(self::GENERATION_TIMEOUT_MINUTES)));
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->currentStatus()] ?? (string) $this->status;
    }

    /** L'ancien écran nommait l'import « custom-<identifiant du fichier> ». */
    public function attachmentKey(): ?string
    {
        if ($this->attachment_id) {
            return $this->attachment_id;
        }

        return str_starts_with((string) $this->id, 'custom-') ? substr((string) $this->id, 7) : null;
    }
}
