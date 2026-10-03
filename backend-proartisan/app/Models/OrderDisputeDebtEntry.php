<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un recouvrement d'une dette de litige. Ligne append-only.
 */
class OrderDisputeDebtEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_dispute_debt_id', 'montant', 'source', 'transaction_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
