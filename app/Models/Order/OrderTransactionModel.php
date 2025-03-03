<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTransactionModel extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'order_transactions';

    protected $fillable = [
        'amount',
        'date',
        'order_id'
    ];

    protected $casts = [
        'amount' => 'double',
    ];

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}
