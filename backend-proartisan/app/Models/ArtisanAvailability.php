<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Version de la disponibilité d'un artisan pour l'annuaire du site vitrine
 * (Chantier 15). Une déclaration de l'artisan attend la validation d'un
 * administrateur ; la version publiée est la dernière validée.
 */
class ArtisanAvailability extends Model
{
    public const STATUS_AVAILABLE = 'disponible';

    public const STATUS_BUSY = 'occupe';

    public const STATUS_LEAVE = 'conge';

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Disponible',
        self::STATUS_BUSY => 'Occupé',
        self::STATUS_LEAVE => 'En congé',
    ];

    public const REVIEW_PENDING = 'en_attente';

    public const REVIEW_APPROVED = 'validee';

    public const REVIEW_REJECTED = 'refusee';

    public const REVIEW_SUPERSEDED = 'remplacee';

    public const REVIEW_LABELS = [
        self::REVIEW_PENDING => 'En attente de validation',
        self::REVIEW_APPROVED => 'Validée',
        self::REVIEW_REJECTED => 'Refusée',
        self::REVIEW_SUPERSEDED => 'Remplacée',
    ];

    public const DAY_LABELS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    protected $fillable = [
        'user_id',
        'status',
        'until_date',
        'schedule_json',
        'night_work',
        'review_status',
        'rejection_reason',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'until_date' => 'date',
            'schedule_json' => 'array',
            'night_work' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Statut à afficher : un « Occupé » ou « En congé » dont la date de
     * retour est passée redevient « Disponible » sans nouvelle validation.
     */
    public function effectiveStatus(?CarbonInterface $today = null): string
    {
        $today ??= now();

        if ($this->status !== self::STATUS_AVAILABLE && $this->until_date !== null && $this->until_date->lt($today->copy()->startOfDay())) {
            return self::STATUS_AVAILABLE;
        }

        return $this->status;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
