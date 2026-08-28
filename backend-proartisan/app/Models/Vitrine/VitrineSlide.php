<?php

namespace App\Models\Vitrine;

use Illuminate\Database\Eloquent\Model;

class VitrineSlide extends Model
{
    protected $table = 'vitrine_slides';

    protected $fillable = [
        'titre',
        'sous_titre',
        'image_url',
        'cta_texte',
        'cta_lien',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre');
    }
}
