<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanProfile extends Model
{
    protected $fillable = [
        'user_id', 'sector_id', 'trade_id', 'bio', 'experience_years', 'photo_url', 'intervient_la_nuit',
    ];

    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'intervient_la_nuit' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function trade()
    {
        return $this->belongsTo(Trade::class);
    }
}
