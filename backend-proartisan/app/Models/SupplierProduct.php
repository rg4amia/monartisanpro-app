<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'sku',
        'description',
        'unit_price',
        'stock_quantity',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function jcodeItems()
    {
        return $this->hasMany(JCodeItem::class);
    }
}
