<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class WarehouseProductModel extends Model
{
    protected $table = 'warehouse_products';
    protected $fillable = [
        'productId',
        'quantity',
        'costPrice',
        'store_id'
    ];

    protected $casts = [
        'productId' => 'string',
        'quantity' => 'integer',
        'costPrice' => 'double',
    ];

    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'productId');
    }
}
