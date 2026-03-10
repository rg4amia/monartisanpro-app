<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'city',
        'country_code',
        'sort_order',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

