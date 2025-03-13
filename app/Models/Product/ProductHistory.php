<?php

namespace App\Models\Product;

use App\Models\GymModel;
use Illuminate\Database\Eloquent\Model;

class ProductHistory extends Model
{
    protected $table = 'product_histories';

    protected $fillable = [
        'product_id',
        'gym_id',
        'description',
        'user_id',
        'type',
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function gym()
    {
        return $this->belongsTo(GymModel::class);
    }
}
