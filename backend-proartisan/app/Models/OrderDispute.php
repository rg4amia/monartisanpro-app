<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Litige ouvert par le client sur une commande de matériaux, et son issue.
 */
class OrderDispute extends Model
{
    public const STATUT_OUVERT = 'ouvert';

    public const STATUT_RESOLU = 'resolu';

    public const STATUT_LABELS = [
        self::STATUT_OUVERT => 'En cours',
        self::STATUT_RESOLU => 'Résolu',
    ];

    /** Issues qu'un administrateur peut prononcer. */
    public const OUTCOMES = ['reclamation_acceptee', 'reclamation_rejetee'];

    public const OUTCOME_LABELS = [
        'reclamation_acceptee' => 'Réclamation du client acceptée',
        'reclamation_rejetee' => 'Réclamation du client rejetée',
        'non_conservee' => 'Issue non conservée',
    ];

    protected $fillable = [
        'order_id', 'opened_by', 'reason', 'statut', 'outcome',
        'resolution_note', 'resolved_by', 'opened_at', 'resolved_at',
        'frozen_amount', 'frozen_user_id', 'refund_amount', 'fare_refund',
        'responsible_role', 'responsible_user_id', 'refund_payout_id',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'frozen_amount' => 'integer',
            'refund_amount' => 'integer',
            'fare_refund' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->statut === self::STATUT_OUVERT;
    }

    public function statutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }

    public function responsibleLabel(): ?string
    {
        return match ($this->responsible_role) {
            'fournisseur' => 'Fournisseur',
            'livreur' => 'Livreur',
            default => null,
        };
    }

    public function outcomeLabel(): ?string
    {
        return $this->outcome ? (self::OUTCOME_LABELS[$this->outcome] ?? $this->outcome) : null;
    }
}
