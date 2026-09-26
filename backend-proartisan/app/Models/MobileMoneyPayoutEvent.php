<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique append-only d'un versement Mobile Money : aucune ligne n'est
 * jamais modifiée ni supprimée (`updating` et `deleting` sont refusés).
 */
class MobileMoneyPayoutEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_TENTATIVE = 'tentative';

    public const ACTION_SUCCES = 'succes';

    public const ACTION_ECHEC = 'echec';

    public const ACTION_RELANCE_MANUELLE = 'relance_manuelle';

    public const ACTION_RELANCE_AUTOMATIQUE = 'relance_automatique';

    public const ACTION_RELANCE_BENEFICIAIRE = 'relance_beneficiaire';

    public const ACTION_VERSEMENT_MANUEL = 'versement_manuel';

    public const ACTION_ANNULATION = 'annulation';

    public const ACTION_CHANGEMENT_NUMERO = 'changement_numero';

    public const ACTION_CHANGEMENT_DESTINATION = 'changement_destination';

    public const ACTION_LABELS = [
        self::ACTION_TENTATIVE => 'Tentative de virement',
        self::ACTION_SUCCES => 'Virement réussi',
        self::ACTION_ECHEC => 'Virement échoué',
        self::ACTION_RELANCE_MANUELLE => 'Relance par un administrateur',
        self::ACTION_RELANCE_AUTOMATIQUE => 'Relance automatique',
        self::ACTION_RELANCE_BENEFICIAIRE => 'Relance par le bénéficiaire',
        self::ACTION_VERSEMENT_MANUEL => 'Versement manuel hors plateforme',
        self::ACTION_ANNULATION => 'Versement annulé',
        self::ACTION_CHANGEMENT_NUMERO => 'Numéro de paiement mis à jour',
        self::ACTION_CHANGEMENT_DESTINATION => 'Moyen de remboursement choisi par le client',
    ];

    protected $fillable = [
        'payout_id',
        'action',
        'statut_avant',
        'statut_apres',
        'message',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyPayout::class, 'payout_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
