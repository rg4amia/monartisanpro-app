<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappClickLog extends Model
{
    protected $fillable = [
        'page',
        'source',
        'referrer',
    ];
}
