<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreModel extends Model
{
    use HasFactory;

    protected $table = 'stores';

    protected $fillable = [
        'store_name',
        'user_id',
        'product_attributes',
    ];

    protected $casts = [
        'product_attributes' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
