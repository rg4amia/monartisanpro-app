<?php

namespace App\Models\Vitrine;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VitrineArticle extends Model
{
    protected $table = 'vitrine_articles';

    protected $fillable = [
        'titre',
        'slug',
        'contenu',
        'image_url',
        'categorie',
        'publie',
        'publie_at',
        'auteur_id',
    ];

    protected $casts = [
        'publie' => 'boolean',
        'publie_at' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function scopePublie($query)
    {
        return $query->where('publie', true);
    }

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->titre) . '-' . Str::random(5);
            }
        });
    }
}
