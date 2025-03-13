<?php

namespace App\Models\Expense;

use App\Models\CashTransaction;
use App\Models\CheckPayable;
use App\Models\GymModel;
use Illuminate\Database\Eloquent\Model;

class ExpenseModel extends Model
{
    protected $table = 'expenses';

    protected $fillable = [
        'id',
        'name',
        'category',
        'date',
        'total',
        'gym_id',
        'description',
        'paid_amount',
        'unpaid_amount',
    ];

    //cast
    protected $casts = [
        'total' => 'float',
        'paid_amount' => 'float',
        'unpaid_amount' => 'float',
    ];

    public function transactions()
    {
        return $this->hasMany(ExpenseTransactionModel::class, 'expense_id');
    }

    public function checkPayable()
    {
        return $this->hasMany(CheckPayable::class, 'expense_id');
    }

    public function gym()
    {
        return $this->belongsTo(GymModel::class, 'gym_id');
    }

    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class, 'expense_id');
    }
}
