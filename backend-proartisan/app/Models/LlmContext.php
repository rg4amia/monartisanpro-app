<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmContext extends Model
{
    protected $table = 'contexts';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'category_id',
        'tags',
        'title',
        'source',
        'execution',
        'pitch',
        'dosages',
        'materials',
        'price',
        'justification',
        'type_ouvrage'
    ];

    protected $casts = [
        'dosages' => 'array',
        'materials' => 'array'
    ];
}
