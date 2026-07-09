<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StagingItem extends Model
{
    protected $table = 'staging_items';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'raw_pdf_source',
        'original_extracted_text',
        'generated_json',
        'status',
        'reviewer_notes',
        'created_at',
        'updated_at',
        'validated_at'
    ];

    protected $casts = [
        'generated_json' => 'array',
    ];
}
