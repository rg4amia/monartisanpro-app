<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versement Mobile Money sortant vers un artisan ou un livreur.
 *
 * Le portefeuille du bénéficiaire n'est débité qu'au virement réussi : tant
 * que le versement est `en_cours` ou `echoue`, les fonds restent sur son
 * portefeuille (et, pour une mission, réservés dans son séquestre).
 */
class MobileMoneyPayout extends Model
{
    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_ECHOUE = 'echoue';

    public const STATUT_VERSE = 'verse';

    public const STATUT_ANNULE = 'annule';

    /** Statuts dont le montant n'a pas encore quitté le portefeuille. */
    public const STATUTS_NON_ABOUTIS = [self::STATUT_EN_COURS, self::STATUT_ECHOUE];

    public const CONTEXT_JALON = 'jalon';

    public const CONTEXT_LITIGE_MO = 'litige_mo';

    public const CONTEXT_LITIGE_MATERIAUX = 'litige_materiaux';

    public const CONTEXT_RETRAIT_LIVREUR = 'retrait_livreur';

    public const STATUT_LABELS = [
        self::STATUT_EN_COURS => 'Virement en cours',
        self::STATUT_ECHOUE => 'Virement échoué',
        self::STATUT_VERSE => 'Versé',
        self::STATUT_ANNULE => 'Annulé',
    ];

    public const CONTEXT_LABELS = [
        self::CONTEXT_JALON => 'Paiement d\'étape de chantier',
        self::CONTEXT_LITIGE_MO => 'Règlement de litige (main-d\'œuvre)',
        self::CONTEXT_LITIGE_MATERIAUX => 'Règlement de litige (matériaux)',
        self::CONTEXT_RETRAIT_LIVREUR => 'Retrait des gains livreur',
    ];

    protected $fillable = [
        'reference',
        'user_id',
        'wallet_type',
        'context',
        'montant',
        'montant_transfere',
        'phone_locked',
        'provider',
        'phone',
        'statut',
        'attempts',
        'last_error',
        'next_retry_at',
        'paid_at',
        'external_reference',
        'mission_id',
        'jalon_id',
        'transaction_id',
        'description',
        'ledger_metadata',
    ];

    protected $casts = [
        'montant' => 'integer',
        'montant_transfere' => 'integer',
        'phone_locked' => 'boolean',
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'paid_at' => 'datetime',
        'ledger_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MobileMoneyPayoutEvent::class, 'payout_id')->orderBy('created_at')->orderBy('id');
    }

    /** Montant effectivement viré (net des frais éventuels). */
    public function transferAmount(): int
    {
        return $this->montant_transfere ?? $this->montant;
    }

    public function isPaid(): bool
    {
        return $this->statut === self::STATUT_VERSE;
    }

    public function isRetryable(): bool
    {
        return $this->statut === self::STATUT_ECHOUE;
    }

    public function statutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }

    public function contextLabel(): string
    {
        return self::CONTEXT_LABELS[$this->context] ?? $this->context;
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'VRS-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
