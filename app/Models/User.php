<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'mobile_number',
        'password',
        'mobile_number_verified_at',
        'store_id', // Add this line
        'user_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'mobile_number_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function store()
    {
        return $this->belongsTo(StoreModel::class);
    }
}
