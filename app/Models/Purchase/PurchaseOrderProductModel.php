<?php


namespace App\Models\Purchase;

use App\Models\PurchaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderProductModel extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_products';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'quantity',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'double',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseModel::class, 'purchase_order_id');
    }
}
