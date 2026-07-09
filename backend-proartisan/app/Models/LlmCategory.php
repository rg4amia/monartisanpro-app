<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmCategory extends Model
{
    protected $table = 'categories';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'profession_id',
        'name',
        'description'
    ];
}
