<?php

namespace App\Models\Vitrine;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitrineArtisanDuMois extends Model
{
    protected $table = 'vitrine_artisan_du_mois';

    protected $fillable = [
        'user_id',
        'mois',
        'photo_override_url',
        'texte_editorial',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'mois' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retourne la photo à afficher : override > selfie KYC.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->photo_override_url) {
            return $this->photo_override_url;
        }

        return $this->user?->kyc_selfie_path;
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function scopeMoisCourant(Builder $query): Builder
    {
        return $query->whereDate('mois', now()->startOfMonth());
    }
}
