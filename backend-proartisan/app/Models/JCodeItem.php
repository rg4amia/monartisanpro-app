<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JCodeItem extends Model
{
    protected $table = 'jcode_items';

    protected $fillable = [
        'jcode_id',
        'supplier_product_id',
        'source',
        'item_name',
        'item_sku',
        'quantity',
        'quantity_served',
        'unit_price',
        'subtotal',
        'status',
        'served_by_supplier_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'integer',
            'quantity_served' => 'integer',
            'unit_price'      => 'integer',
            'subtotal'        => 'integer',
        ];
    }

    public function jcode()
    {
        return $this->belongsTo(JCode::class, 'jcode_id');
    }

    public function supplierProduct()
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function servedBySupplier()
    {
        return $this->belongsTo(User::class, 'served_by_supplier_id');
    }

    /**
     * Quantité restante à servir pour cet item.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - ($this->quantity_served ?? 0));
    }
}
