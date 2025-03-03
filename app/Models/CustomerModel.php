<?php

namespace App\Models;

use App\Models\Order\OrderModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerModel extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'customers';

    protected $fillable = [
        'id',
        'name',
        'phoneNumber',
        'createdOn',
        'store_id',
        'debt',
    ];

    protected $casts = [
        'debt' => 'double',
    ];

    public function store()
    {
        return $this->belongsTo(StoreModel::class);
    }

    public function orders()
    {
        return $this->hasMany(OrderModel::class, 'customerId');
    }

    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class, 'customer_id');
    }

    public function checkReceivables()
    {
        return $this->hasMany(CheckReceivable::class, 'customer_id');
    }

    public function getUnpaidOrdersAmount()
    {
        return $this->orders->sum('unpaid_amount');
    }

}
