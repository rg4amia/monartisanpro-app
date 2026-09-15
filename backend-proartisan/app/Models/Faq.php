<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question',
        'reponse',
        'categorie',
        'roles',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'roles' => 'array',
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('ordre')->orderBy('id');
    }

    public function scopePourRole(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('roles', $role);
    }
}
