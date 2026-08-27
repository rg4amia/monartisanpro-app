<?php

namespace App\Models;

use App\States\Mission\CompletedState;
use App\States\Mission\DisputedState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\MissionState;
use App\States\Mission\PendingApprovalState;
use App\States\Mission\PendingFundingState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

class Mission extends Model
{
    use HasFactory, HasStates;

    protected $fillable = [
        'client_id', 'artisan_id', 'description', 'photos_json',
        'requested_sector_id', 'requested_trade_id',
        'gemini_category', 'gemini_urgency', 'gemini_estimation_min', 'gemini_estimation_max',
        'status', 'montant_total', 'montant_materiaux', 'montant_mo',
        'ratio_materiaux', 'referent_required', 'funds_frozen',
        'referent_validated_at', 'referent_validated_by',
        'client_latitude', 'client_longitude', 'client_address',
        'payment_type',
    ];

    protected function casts(): array
    {
        return [
            'status'              => MissionState::class,
            'photos_json'         => 'array',
            'montant_total'       => 'integer',
            'montant_materiaux'   => 'integer',
            'montant_mo'          => 'integer',
            'ratio_materiaux'     => 'decimal:4',
            'referent_required'   => 'boolean',
            'funds_frozen'        => 'boolean',
            'gemini_estimation_min' => 'integer',
            'gemini_estimation_max' => 'integer',
            'referent_validated_at' => 'datetime',
            'client_latitude'       => 'float',
            'client_longitude'      => 'float',
        ];
    }

    // ──────────────────────────────────────────────
    // Guards métier (utilisés par les services)
    // ──────────────────────────────────────────────

    public function isFundsFrozen(): bool
    {
        return (bool) $this->funds_frozen || $this->status instanceof DisputedState;
    }

    public function isCompleted(): bool
    {
        return $this->status instanceof CompletedState;
    }

    public function isDisputed(): bool
    {
        return $this->status instanceof DisputedState;
    }

    public function canGenerateJCode(): bool
    {
        return $this->status instanceof FundedLockedState
            || $this->status instanceof InProgressState;
    }

    public function canSubmitJalon(): bool
    {
        return $this->status instanceof InProgressState;
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function referent()
    {
        return $this->belongsTo(User::class, 'referent_validated_by');
    }

    public function requestedSector()
    {
        return $this->belongsTo(Sector::class, 'requested_sector_id');
    }

    public function requestedTrade()
    {
        return $this->belongsTo(Trade::class, 'requested_trade_id');
    }

    public function devis()
    {
        return $this->hasMany(Devis::class);
    }

    public function jalons()
    {
        return $this->hasMany(Jalon::class)->orderBy('ordre');
    }

    public function jcodes()
    {
        return $this->hasMany(JCode::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function litiges()
    {
        return $this->hasMany(Litige::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function devisAccepte()
    {
        return $this->hasOne(Devis::class)->where('statut', 'accepte')->where('is_avenant', false);
    }

    public function devisAvenants()
    {
        return $this->hasMany(Devis::class)->where('is_avenant', true);
    }

    public function hasPendingDevis(): bool
    {
        return $this->devis()->where('statut', 'soumis')->exists();
    }
}
