<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditApplication extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'score_nzassa_at_application', 'status',
        'external_reference', 'approved_at', 'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'score_nzassa_at_application' => 'integer',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
