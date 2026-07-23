<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanStock extends Model
{
    protected $fillable = [
        'artisan_id', 'description', 'quantity', 'unit_cost', 'condition'
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
