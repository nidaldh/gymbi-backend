<?php

namespace App\Models\Expense;

use Illuminate\Database\Eloquent\Model;

class ExpenseTransactionModel extends Model
{

    protected $table = 'expense_transactions';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'expense_id',
        'amount',
        'date',
        'description',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function expense()
    {
        return $this->belongsTo(ExpenseModel::class, 'expense_id');
    }
}
