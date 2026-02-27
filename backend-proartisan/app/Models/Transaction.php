<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'mission_id', 'user_id', 'type', 'montant',
        'wallet_source', 'wallet_dest', 'provider', 'statut', 'reference_externe',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
        ];
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
