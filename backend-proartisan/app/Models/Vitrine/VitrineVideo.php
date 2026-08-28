<?php

namespace App\Models\Vitrine;

use Illuminate\Database\Eloquent\Model;

class VitrineVideo extends Model
{
    protected $table = 'vitrine_videos';

    protected $fillable = [
        'titre',
        'description',
        'video_url',
        'thumbnail_url',
        'categorie',
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
