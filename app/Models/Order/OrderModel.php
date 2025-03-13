<?php

namespace App\Models\Order;

use App\Models\CashTransaction;
use App\Models\CheckReceivable;
use App\Models\MemberModel;
use App\Models\GymModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'orders';

    protected $fillable = [
        'totalPrice',
        'totalDiscount',
        'member_id',
        'paidAmount',
        'gym_id',
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

    public function member()
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
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

    public function gym()
    {
        return $this->belongsTo(GymModel::class, 'gym_id');
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
