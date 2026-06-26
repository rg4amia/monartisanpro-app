<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'supplier_product_id', 'quantity', 'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'order_id'            => 'integer',
            'supplier_product_id' => 'integer',
            'quantity'            => 'integer',
            'unit_price'          => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(SupplierProduct::class, 'supplier_product_id');
    }
}
