<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Litige extends Model
{
    protected $fillable = [
        'mission_id',
        'declencheur_id',
        'type',
        'motif',
        'description',
        'statut',
        'workflow_step',
        'decision',
        'funds_locked_at',
        'evidence_deadline_at',
        'arbitration_started_at',
        'arbitration_deadline_at',
        'client_evidence_submitted_at',
        'artisan_evidence_submitted_at',
        'resolu_at',
        'resolved_by',
        'admin_notes',
        'resolution_reason',
        'resolution_payload',
        'sanctions_json',
    ];

    protected function casts(): array
    {
        return [
            'funds_locked_at' => 'datetime',
            'evidence_deadline_at' => 'datetime',
            'arbitration_started_at' => 'datetime',
            'arbitration_deadline_at' => 'datetime',
            'client_evidence_submitted_at' => 'datetime',
            'artisan_evidence_submitted_at' => 'datetime',
            'resolu_at' => 'datetime',
            'resolution_payload' => 'array',
            'sanctions_json' => 'array',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function declencheur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declencheur_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function preuves(): HasMany
    {
        return $this->hasMany(LitigeEvidence::class)->orderByDesc('created_at');
    }

    public function isResolved(): bool
    {
        return $this->statut === 'resolu';
    }
}
