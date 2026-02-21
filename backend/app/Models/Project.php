<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Project extends Model
{
    use SoftDeletes, HasSpatial;

    protected $fillable = [
        'client_id',
        'artisan_id',
        'trade_id',
        'title',
        'description',
        'location',
        'address',
        'expected_completion_date',
        'status',
        'budget_min',
        'budget_max',
        'final_amount',
        'quote_count',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'location' => Point::class,
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'quote_count' => 'integer',
        'expected_completion_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function escrowWallet(): HasOne
    {
        return $this->hasOne(EscrowWallet::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class)->orderBy('created_at');
    }
}
