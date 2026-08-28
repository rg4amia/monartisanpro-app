<?php

namespace App\Models\Vitrine;

use Illuminate\Database\Eloquent\Model;

class VitrineRecrutement extends Model
{
    protected $table = 'vitrine_recrutements';

    protected $fillable = [
        'titre',
        'description',
        'metier',
        'lieu',
        'type_contrat',
        'date_limite',
        'contact_email',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'date_limite' => 'date',
    ];

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeOuvert($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('date_limite')
              ->orWhere('date_limite', '>=', now()->toDateString());
        });
    }
}
