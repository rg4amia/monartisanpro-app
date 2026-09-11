<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'document_type',
        'title',
        'user_id',
        'mission_id',
        'transaction_id',
        'supplier_cashout_id',
        'litige_id',
        'montant',
        'file_path',
        'mime_type',
        'file_size',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function supplierCashout(): BelongsTo
    {
        return $this->belongsTo(SupplierCashout::class);
    }

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('reference', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
        });
    }

    public function scopeOfType($query, ?string $type)
    {
        if (! $type || $type === 'all') {
            return $query;
        }

        return $query->where('document_type', $type);
    }
}
