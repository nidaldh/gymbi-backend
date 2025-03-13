<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorModel extends Model
{
    use HasFactory;

    protected $table = 'vendors';

    protected $fillable = [
        'name',
        'phone',
        'debt',
        'gym_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'debt' => 'double',
    ];

    public function purchases()
    {
        return $this->hasMany(PurchaseModel::class, 'vendor_id', 'id');
    }

    public function checkPayable()
    {
        return $this->hasMany(CheckPayable::class, 'vendor_id', 'id');
    }


    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class, 'vendor_id', 'id');
    }

    public function gym()
    {
        return $this->belongsTo(GymModel::class, 'gym_id');
    }

}
