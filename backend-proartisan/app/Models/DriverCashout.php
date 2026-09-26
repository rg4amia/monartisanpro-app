<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de retrait des gains d'un livreur (modèle du cash-out quincaillerie).
 * Cycle : en_attente → approuve → complete, ou rejete.
 */
class DriverCashout extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_APPROUVE = 'approuve';

    public const STATUT_COMPLETE = 'complete';

    public const STATUT_REJETE = 'rejete';

    /** Demandes dont le montant est réservé sur le portefeuille. */
    public const STATUTS_RESERVES = [self::STATUT_EN_ATTENTE, self::STATUT_APPROUVE];

    public const MODE_WAVE = 'wave';

    public const MODE_ORANGE_MONEY = 'orange_money';

    public const MODE_VIREMENT_BANCAIRE = 'virement_bancaire';

    public const MODES = [self::MODE_WAVE, self::MODE_ORANGE_MONEY, self::MODE_VIREMENT_BANCAIRE];

    public const STATUT_LABELS = [
        self::STATUT_EN_ATTENTE => 'En attente',
        self::STATUT_APPROUVE => 'Approuvé',
        self::STATUT_COMPLETE => 'Versé',
        self::STATUT_REJETE => 'Rejeté',
    ];

    protected $fillable = [
        'reference',
        'driver_id',
        'beneficiary_name',
        'beneficiary_phone',
        'bank_name',
        'bank_account_number',
        'montant_brut',
        'commission_rate',
        'montant_commission',
        'montant_net',
        'statut',
        'mode_retrait',
        'notes',
        'payout_id',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'montant_brut' => 'integer',
        'commission_rate' => 'decimal:4',
        'montant_commission' => 'integer',
        'montant_net' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MobileMoneyPayout::class, 'payout_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function statutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'RTL-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
