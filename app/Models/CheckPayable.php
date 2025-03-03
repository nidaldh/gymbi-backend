<?php

namespace App\Models;

use App\Models\Expense\ExpenseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckPayable extends Model
{
    use HasFactory;

    protected $table = 'check_payable';

    protected $fillable = [
        'check_number',
        'bank_id',
        'issuer_name',
        'payee_name',
        'amount',
        'status',
        'due_date',
        'expense_id',
        'store_id',
        'vendor_id',
    ];

    public function expense()
    {
        return $this->belongsTo(ExpenseModel::class, 'expense_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorModel::class, 'vendor_id');
    }
}
