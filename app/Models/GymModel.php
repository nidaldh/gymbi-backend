<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymModel extends Model
{
    use HasFactory;

    protected $table = 'gyms';

    protected $fillable = [
        'name',
        'user_id',
        'product_attributes',
        'enable_checks',
        'enable_vendors',
        'enable_expenses',
        'enable_cash_transactions',
        'enable_product_attributes',
        'enable_products',
    ];

    protected $casts = [
        'product_attributes' => 'array',
        'enable_checks' => 'boolean',
        'enable_vendors' => 'boolean',
        'enable_expenses' => 'boolean',
        'enable_cash_transactions' => 'boolean',
        'enable_product_attributes' => 'boolean',
        'enable_products' => 'boolean'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
