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
        'unit_price',
        'subtotal',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
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
}
