<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportHistory extends Model
{
    protected $table = 'import_history';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'filename',
        'file_size',
        'imported_at',
        'status',
        'vlm_extracted',
        'llm_downscaled'
    ];

    protected $casts = [
        'vlm_extracted' => 'boolean',
        'llm_downscaled' => 'boolean',
    ];
}
