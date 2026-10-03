<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fiche technique en attente de relecture, avant publication vers l'Assistant IA.
 */
class StagingItem extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_WITHDRAWN = 'WITHDRAWN';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'À relire',
        self::STATUS_APPROVED => 'Publiée',
        self::STATUS_REJECTED => 'Rejetée',
        self::STATUS_WITHDRAWN => 'Retirée',
    ];

    public const ORIGIN_AI = 'ia';

    public const ORIGIN_MANUAL = 'manuelle';

    protected $table = 'staging_items';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'raw_pdf_source',
        'original_extracted_text',
        'generated_json',
        'status',
        'reviewer_notes',
        'import_id',
        'origin',
        'model_name',
        'created_by_id',
        'validated_by_id',
        'created_at',
        'updated_at',
        'validated_at',
    ];

    protected $casts = [
        'generated_json' => 'array',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }
}
