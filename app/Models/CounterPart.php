<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounterPart extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'counterparts';
}
