<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCashout extends Model
{
    use HasFactory;

    protected $table = 'supplier_cashouts';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVE = 'approuve';
    public const STATUT_COMPLETE = 'complete';
    public const STATUT_REJETE = 'rejete';

    public const MODE_ESPECES_GUICHET = 'especes_guichet';
    public const MODE_WAVE = 'wave';
    public const MODE_ORANGE_MONEY = 'orange_money';
    public const MODE_VIREMENT_BANCAIRE = 'virement_bancaire';

    protected $fillable = [
        'reference',
        'supplier_id',
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
        'batch_reference',
        'processed_by',
        'processed_at',
        'reconciled_by',
        'reconciled_at',
    ];

    protected $casts = [
        'montant_brut' => 'integer',
        'commission_rate' => 'decimal:4',
        'montant_commission' => 'integer',
        'montant_net' => 'integer',
        'processed_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeApprouve($query)
    {
        return $query->where('statut', 'approuve');
    }

    public function scopeComplete($query)
    {
        return $query->where('statut', 'complete');
    }

    /**
     * Génère une référence unique pour le retrait cash (format CSH-YYYYMMDD-XXXX).
     */
    public static function generateReference(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(2)));
        $ref = "CSH-{$date}-{$random}";

        while (static::where('reference', $ref)->exists()) {
            $random = strtoupper(bin2hex(random_bytes(2)));
            $ref = "CSH-{$date}-{$random}";
        }

        return $ref;
    }
}
