<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    protected $fillable = ['name'];

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }
}
