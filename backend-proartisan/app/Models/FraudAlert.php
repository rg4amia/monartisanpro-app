<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FraudAlert extends Model
{
    use HasFactory;

    protected $table = 'fraud_alerts';

    protected $fillable = [
        'reference',
        'mission_id',
        'user_id',
        'target_user_id',
        'type',
        'severity',
        'risk_score',
        'reasons_json',
        'metadata_json',
        'statut',
        'action_taken',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'reasons_json' => 'array',
        'metadata_json' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Génère une référence unique d'alerte de fraude (FRD-YYYYMMDD-XXXX).
     */
    public static function generateReference(): string
    {
        $datePrefix = date('Ymd');
        $random = strtoupper(Str::random(4));
        $ref = "FRD-{$datePrefix}-{$random}";

        while (static::where('reference', $ref)->exists()) {
            $random = strtoupper(Str::random(4));
            $ref = "FRD-{$datePrefix}-{$random}";
        }

        return $ref;
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOuvertes(Builder $query): Builder
    {
        return $query->whereIn('statut', ['ouverte', 'en_analyse']);
    }

    public function scopeCritiques(Builder $query): Builder
    {
        return $query->where('severity', 'critical')
            ->orWhere('risk_score', '>=', 75);
    }

    public function scopeRecentes(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
