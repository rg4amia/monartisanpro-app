<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Communication extends Model
{
    /** Publication portant un enregistrement vocal téléversé. */
    public const TYPE_AUDIO = 'audio';

    /** Publication renvoyant vers une vidéo hébergée à l'extérieur. */
    public const TYPE_VIDEO = 'video';

    /** Types acceptés, du plus ancien au plus récent. */
    public const TYPES = ['annonce', 'le_saviez_vous', self::TYPE_AUDIO, self::TYPE_VIDEO];

    protected $fillable = [
        'type',
        'titre',
        'contenu',
        'cibles_json',
        'statut',
        'auteur_id',
        'publie_at',
        'cloture_at',
        'media_path',
        'media_external_url',
        'media_mime',
        'media_size',
        'media_duration',
    ];

    /**
     * `media_url` est calculée : le mobile lit une seule clé, qu'il s'agisse
     * d'un fichier téléversé ou d'un lien externe.
     */
    protected $appends = ['media_url'];

    protected function casts(): array
    {
        return [
            'cibles_json'    => 'array',
            'publie_at'      => 'datetime',
            'cloture_at'     => 'datetime',
            'media_size'     => 'integer',
            'media_duration' => 'integer',
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

    public function scopeAudio($query)
    {
        return $query->where('type', self::TYPE_AUDIO);
    }

    public function scopeVideo($query)
    {
        return $query->where('type', self::TYPE_VIDEO);
    }

    // ── Média ────────────────────────────────────────────────────────────────

    /**
     * URL de lecture, quelle que soit la provenance du média.
     *
     * Un contenu vocal est téléversé sur le disque public ; une vidéo pointe
     * vers sa plateforme d'hébergement. Exposer une clé unique évite au mobile
     * d'avoir à connaître cette distinction.
     */
    public function getMediaUrlAttribute(): ?string
    {
        if ($this->media_external_url) {
            return $this->media_external_url;
        }

        return $this->media_path
            ? Storage::disk('public')->url($this->media_path)
            : null;
    }

    public function isAudio(): bool
    {
        return $this->type === self::TYPE_AUDIO;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    /** Une publication média sans média est inexploitable côté mobile. */
    public function hasPlayableMedia(): bool
    {
        return $this->media_url !== null;
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

    /**
     * Modifiable et supprimable : brouillon ou clôturée.
     *
     * Une publication clôturée doit pouvoir être corrigée puis rediffusée,
     * sans obliger l'administrateur à tout ressaisir. Une publication en
     * cours de diffusion reste figée : elle changerait sous les yeux de ceux
     * qui la consultent.
     */
    public function isEditable(): bool
    {
        return $this->isBrouillon() || $this->isCloture();
    }

    /** Publiable : jamais déjà en diffusion. */
    public function isPublishable(): bool
    {
        return ! $this->isPublie();
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
