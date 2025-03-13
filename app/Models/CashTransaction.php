<?php

namespace App\Models;

use App\Models\Expense\ExpenseModel;
use App\Models\Order\OrderModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    use HasFactory;

    protected $table = 'cash_transactions';

    protected $fillable = [
        'customer_id',
        'expense_id',
        'check_receivable_id',
        'check_payable_id',
        'amount',
        'gym_id',
        'vendor_id',
        'notes',
        'order_id',
    ];

    protected $casts = [
        'amount' => 'double',
    ];

    public function customer()
    {
        return $this->belongsTo(MemberModel::class, 'customer_id');
    }

    public function expense()
    {
        return $this->belongsTo(ExpenseModel::class, 'expense_id');
    }

    public function checkReceivable()
    {
        return $this->belongsTo(CheckReceivable::class, 'check_receivable_id');
    }

    public function checkPayable()
    {
        return $this->belongsTo(CheckPayable::class, 'check_payable_id');
    }

    public function gym()
    {
        return $this->belongsTo(GymModel::class, 'gym_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorModel::class, 'vendor_id');
    }

    public function order()
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }
}
