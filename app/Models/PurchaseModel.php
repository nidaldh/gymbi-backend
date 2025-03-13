<?php

namespace App\Models;

use App\Models\Purchase\PurchaseOrderProductModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseModel extends Model
{
    use HasFactory;

    protected $table = 'purchases';

    protected $fillable = [
        'vendor_id',
        'total',
        'unpaid_amount',
        'notes',
        'date',
        'gym_id',
        'discount',
        'sub_total',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total' => 'double',
        'unpaid_amount' => 'double',
        'discount' => 'double',
        'sub_total' => 'double',
    ];


    public function vendor()
    {
        return $this->belongsTo(VendorModel::class, 'vendor_id');
    }

    public function products()
    {
        return $this->hasMany(PurchaseOrderProductModel::class, 'purchase_order_id');
    }

}
