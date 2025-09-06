<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryPriceVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'history_prices';
}
