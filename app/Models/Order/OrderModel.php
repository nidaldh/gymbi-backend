<?php

namespace App\Models\Order;

use App\Models\CashTransaction;
use App\Models\CheckReceivable;
use App\Models\CustomerModel;
use App\Models\StoreModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'orders';

    protected $fillable = [
        'old_id',
        'totalPrice',
        'totalDiscount',
        'customerId',
        'paidAmount',
        'store_id',
        'totalCost',
        'created_at',
        'unpaid_amount',
    ];

    protected $casts = [
        'totalPrice' => 'double',
        'totalDiscount' => 'double',
        'paidAmount' => 'double',
        'totalCost' => 'double',
        'unpaid_amount' => 'double',
    ];

    public function customer()
    {
        return $this->belongsTo(CustomerModel::class, 'customerId');
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProductModel::class, 'order_id');
    }

    public function orderTransactions()
    {
        return $this->hasMany(OrderTransactionModel::class, 'order_id');
    }

    public function calculateOrderPaidAmount($order_id)
    {
        $order = OrderModel::findOrFail($order_id);
        $order->paidAmount = $order->orderTransactions->sum('amount');
        $order->save();
    }

    public function store()
    {
        return $this->belongsTo(StoreModel::class, 'store_id');
    }

    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class, 'order_id');
    }

    public function checkReceivables()
    {
        return $this->hasMany(CheckReceivable::class, 'order_id');
    }
}
