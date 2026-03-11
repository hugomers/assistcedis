<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEva extends Model
{
    protected $connection = 'eva';
    protected $table = 'users';

}
