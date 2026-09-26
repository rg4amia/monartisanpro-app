<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoubleEntryLedgerEntry extends Model
{
    public $timestamps = false;

    protected $table = 'double_entry_ledger_entries';

    protected $fillable = [
        'transaction_group_id',
        'account_source',
        'account_destination',
        'amount',
        'currency',
        'entry_type',
        'mission_id',
        'jalon_id',
        'order_id',
        'user_id',
        'description',
        'metadata_json',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function jalon(): BelongsTo
    {
        return $this->belongsTo(Jalon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
