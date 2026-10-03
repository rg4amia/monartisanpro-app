<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somme due à ProsArtisan par le responsable d'un litige de commande : la
 * part du remboursement du client que son portefeuille ne couvrait plus.
 */
class OrderDisputeDebt extends Model
{
    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_SOLDEE = 'soldee';

    public const STATUT_ANNULEE = 'annulee';

    public const STATUT_LABELS = [
        self::STATUT_EN_COURS => 'À rembourser',
        self::STATUT_SOLDEE => 'Soldée',
        self::STATUT_ANNULEE => 'Annulée',
    ];

    public const SOURCE_LABELS = [
        'prelevement_gains' => 'Prélèvement sur vos gains',
        'reglement_direct' => 'Règlement par Mobile Money',
    ];

    protected $fillable = [
        'order_dispute_id', 'user_id', 'wallet_type', 'montant', 'montant_recouvre',
        'statut', 'settled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'montant_recouvre' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(OrderDispute::class, 'order_dispute_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OrderDisputeDebtEntry::class)->orderByDesc('id');
    }

    public function remaining(): int
    {
        return max(0, (int) $this->montant - (int) $this->montant_recouvre);
    }

    public function isOpen(): bool
    {
        return $this->statut === self::STATUT_EN_COURS;
    }

    public function statutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }
}
