<?php

namespace App\Models;

use App\Models\Order\OrderModel;
use App\Models\Subscription\SubscriptionModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberModel extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'members';

    protected $fillable = [
        'name',
        'mobile',
        'date_of_birth',
        'gender',
        'gym_id',
        'debt',
    ];

    protected $casts = [
        'debt' => 'double',
    ];

    public function gym()
    {
        return $this->belongsTo(GymModel::class);
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

    public function subscriptions()
    {
        return $this->hasMany(SubscriptionModel::class, 'member_id');
    }

}
