<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashCount extends Model
{
     protected $table = "cash_counts";
    protected $fillable = [
        "_cashier",
        "_created_by",
        "current_cash",
        "counted_cash",
        "discrepancy",
        "details",
    ];
}
