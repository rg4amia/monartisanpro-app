<?php

namespace App\Models\Vitrine;

use Illuminate\Database\Eloquent\Model;

class VitrinePopup extends Model
{
    protected $table = 'vitrine_popups';

    protected $fillable = [
        'titre',
        'contenu',
        'image_url',
        'lien_cta',
        'texte_cta',
        'date_debut',
        'date_fin',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
    ];

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeEnCours($query)
    {
        $now = now();
        return $query->where('date_debut', '<=', $now)
                     ->where('date_fin', '>=', $now);
    }
}
