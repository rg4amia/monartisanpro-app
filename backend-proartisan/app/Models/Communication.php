<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    protected $fillable = [
        'type',
        'titre',
        'contenu',
        'cibles_json',
        'statut',
        'auteur_id',
        'publie_at',
        'cloture_at',
    ];

    protected function casts(): array
    {
        return [
            'cibles_json' => 'array',
            'publie_at'   => 'datetime',
            'cloture_at'  => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublie($query)
    {
        return $query->where('statut', 'publie');
    }

    public function scopeForRole($query, string $role)
    {
        return $query->whereJsonContains('cibles_json', $role);
    }

    public function scopeAnnonces($query)
    {
        return $query->where('type', 'annonce');
    }

    public function scopeLeSaviezVous($query)
    {
        return $query->where('type', 'le_saviez_vous');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isBrouillon(): bool
    {
        return $this->statut === 'brouillon';
    }

    public function isPublie(): bool
    {
        return $this->statut === 'publie';
    }

    public function isCloture(): bool
    {
        return $this->statut === 'cloture';
    }

    public function ciblesLabel(): string
    {
        $labels = [
            'client'      => 'Client',
            'artisan'     => 'Artisan',
            'fournisseur' => 'Fournisseur',
            'livreur'     => 'Livreur',
        ];

        return collect($this->cibles_json)
            ->map(fn(string $cible) => $labels[$cible] ?? $cible)
            ->implode(', ');
    }
}
