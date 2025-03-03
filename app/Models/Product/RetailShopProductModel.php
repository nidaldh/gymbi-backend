<?php


namespace App\Models\Product;


class RetailShopProductModel extends ProductModel
{

    protected $fillable = [
        'name',
        'quantity',
        'salePrice',
        'costPrice',
        'minPrice',
        'description',
        'quantityPerBox',
        'location',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'salePrice' => 'float',
        'costPrice' => 'float',
        'minPrice' => 'float',
        'quantityPerBox' => 'integer',
    ];

    public function getTable()
    {
        return $this->getCollection();
    }
}
