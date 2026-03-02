<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundLog extends Model
{
    protected $table = "transfer_store_logs";
    protected $fillable = [
        '_transfer',
        '_state',
        '_user',
        'details',
    ];
}
