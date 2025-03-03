<?php


namespace App\Models\Product;


class TiresAndRepairsProductModel extends ProductModel
{
    protected $fillable = [
        'id',
        'name',
        'quantity',
        'salePrice',
        'costPrice',
        'addedBy',
        'editedBy',
        'addedOn',
        'editedOn',
        'status',
        'brandName',
        'store_id',
        'old_id'
    ];

    public function getTable()
    {
        return $this->getCollection();
    }
}
