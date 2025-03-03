<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpModel extends Model
{
    use HasFactory;

    protected $table = 'otps';
    protected $fillable = ['mobile_number', 'otp'];
    public $timestamps = false;
}
