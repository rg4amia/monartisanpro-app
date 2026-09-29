<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destinataire déjà servi par une campagne : la clé unique (campagne,
 * utilisateur) empêche tout second envoi.
 */
class NotificationCampaignRecipient extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['campaign_id', 'user_id', 'notification_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
