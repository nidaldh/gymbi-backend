<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckReceivable extends Model
{
    use HasFactory;

    protected $table = 'check_receivable';

    protected $fillable = [
        'check_number',
        'bank_id',
        'issuer_name',
        'payee_name',
        'amount',
        'status',
        'due_date',
        'customer_id',
        'gym_id',
    ];

    protected $casts = [
        'amount' => 'double',
    ];

    public function customer()
    {
        return $this->belongsTo(MemberModel::class, 'customer_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
