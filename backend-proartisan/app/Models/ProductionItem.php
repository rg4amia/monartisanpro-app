<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionItem extends Model
{
    protected $table = 'production_items';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'generated_json',
        'tags'
    ];

    protected $casts = [
        'generated_json' => 'array',
    ];
}
