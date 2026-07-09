<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmAttachment extends Model
{
    protected $table = 'attachments';
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;

    protected $fillable = [
        'id',
        'original_filename',
        'extension',
        'file_link',
        'uploaded_by',
        'created_at'
    ];
}
