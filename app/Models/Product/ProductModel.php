<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class ProductModel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $fillable = [
        'name',
        'quantity',
        'salePrice',
        'costPrice',
        'attributes',
        'isCountable',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'salePrice' => 'float',
        'costPrice' => 'float',
    ];

    public function getCollection(): string
    {
        return auth()->user()->store_id . '_products';
    }

    public function getTable()
    {
        return $this->getCollection();
    }
}
