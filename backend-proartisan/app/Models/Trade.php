<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = ['sector_id', 'name'];

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function artisanProfiles()
    {
        return $this->hasMany(ArtisanProfile::class);
    }
}
