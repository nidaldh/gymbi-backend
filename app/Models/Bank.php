<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $table = 'banks';

    protected $fillable = [
        'name_ar',
    ];

    public function checkReceivables()
    {
        return $this->hasMany(CheckReceivable::class, 'bank_id');
    }

    public function checkPayables()
    {
        return $this->hasMany(CheckPayable::class, 'bank_id');
    }
}
