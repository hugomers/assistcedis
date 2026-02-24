<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferWarehouseLog extends Model
{
     protected $table = "transfer_warehouse_logs";
        protected $fillable = [
        '_transfer',
        '_state',
        '_user',
        'details',
    ];
}
