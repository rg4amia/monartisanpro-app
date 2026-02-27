<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    protected $fillable = ['name', 'icon', 'color'];

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    public function artisanProfiles()
    {
        return $this->hasMany(ArtisanProfile::class);
    }
}
