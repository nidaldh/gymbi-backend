<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProductModel extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'order_products';

    protected $fillable = [
        'order_id',
        'productId',
        'name',
        'quantity',
        'price',
        'costPrice'
    ];

    protected $casts = [
        'price' => 'double',
        'costPrice' => 'double',
    ];

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}
