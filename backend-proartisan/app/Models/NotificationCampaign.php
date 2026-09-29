<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campagne push et SMS rédigée depuis le backoffice (Chantier 14, lot D).
 * Modifiable et supprimable tant qu'elle n'est pas partie ; envoyée par
 * lots par la commande planifiée `notifications:send-campaigns`.
 */
class NotificationCampaign extends Model
{
    public const STATUS_DRAFT = 'brouillon';

    public const STATUS_SCHEDULED = 'programmee';

    public const STATUS_SENDING = 'en_cours';

    public const STATUS_SENT = 'envoyee';

    public const STATUS_CANCELLED = 'annulee';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_SCHEDULED => 'Programmée',
        self::STATUS_SENDING => 'En cours d\'envoi',
        self::STATUS_SENT => 'Envoyée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    public const NATURE_SERVICE = 'service';

    public const NATURE_PROMOTIONAL = 'promotionnel';

    public const NATURE_LABELS = [
        self::NATURE_SERVICE => 'Information de service',
        self::NATURE_PROMOTIONAL => 'Promotionnel',
    ];

    public const SCREEN_LABELS = [
        'notifications' => 'Liste des notifications',
        'home' => 'Accueil',
        'communication' => 'Communication publiée (accueil)',
    ];

    protected $fillable = [
        'name',
        'nature',
        'push_title',
        'push_body',
        'sms_body',
        'channel_in_app',
        'channel_push',
        'channel_sms',
        'target_json',
        'open_screen',
        'communication_id',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'cancelled_at',
        'recipients_count',
        'served_count',
        'sent_count',
        'failed_count',
        'created_by',
        'updated_by',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'channel_in_app' => 'boolean',
            'channel_push' => 'boolean',
            'channel_sms' => 'boolean',
            'target_json' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'recipients_count' => 'integer',
            'served_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    /** Modifiable et supprimable : elle n'est pas encore partie. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_SENDING], true);
    }

    /** Une campagne annulée avant d'avoir servi quiconque peut disparaître. */
    public function isDeletable(): bool
    {
        return $this->isEditable() || ($this->status === self::STATUS_CANCELLED && $this->served_count === 0);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationCampaignRecipient::class, 'campaign_id');
    }
}
