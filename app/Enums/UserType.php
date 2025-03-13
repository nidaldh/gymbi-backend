<?php

namespace App\Enums;


use Illuminate\Validation\Rules\Enum;

final class UserType extends Enum
{
    const ADMIN = 'admin';
    const EMPLOYEE = 'employee';
}
