<?php

namespace App\Models\Vitrine;

use Illuminate\Database\Eloquent\Model;

class VitrineFormation extends Model
{
    protected $table = 'vitrine_formations';

    protected $fillable = [
        'titre',
        'description',
        'image_url',
        'date_debut',
        'date_fin',
        'lieu',
        'formateur',
        'places_total',
        'places_restantes',
        'tarif',
        'lien_inscription',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'tarif' => 'integer',
        'places_total' => 'integer',
        'places_restantes' => 'integer',
    ];

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeAVenir($query)
    {
        return $query->where('date_debut', '>=', now()->toDateString());
    }
}
