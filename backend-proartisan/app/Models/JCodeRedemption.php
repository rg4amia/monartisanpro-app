<?php

namespace App\Models;

use App\Casts\PrivateMediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JCodeRedemption extends Model
{
    protected $table = 'jcode_redemptions';

    protected $fillable = [
        'jcode_id',
        'fournisseur_id',
        'montant',
        'recu_photo_url',
        'latitude',
        'longitude',
        'items_json',
        'scanned_at',
    ];

    protected $casts = [
        'recu_photo_url' => PrivateMediaUrl::class,
        'montant' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'items_json' => 'array',
        'scanned_at' => 'datetime',
    ];

    public function jcode(): BelongsTo
    {
        return $this->belongsTo(JCode::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fournisseur_id');
    }
}
