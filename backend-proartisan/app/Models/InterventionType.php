<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterventionType extends Model
{
    protected $fillable = [
        'name',
        'requires_labor',
    ];

    protected function casts(): array
    {
        return [
            'requires_labor' => 'boolean',
        ];
    }
}
