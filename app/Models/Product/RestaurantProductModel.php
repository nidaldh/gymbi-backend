<?php


namespace App\Models\Product;


class RestaurantProductModel extends ProductModel
{
    protected $fillable = [
        'id',
        'name',
        'quantity',
        'salePrice',
        'costPrice',
        'countable',
        'editedOn',
        'addedOn',
    ];

    public function getTable()
    {
        return $this->getCollection();
    }
}
