<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashReceipt extends Model
{
     protected $table = "cash_receipt";
    protected $fillable = [
        "_cash",
        "_cashier",
        "cash_receipt",
        "cash_expenses",
        "cash_send",
        "discrepancy",
        "open_date",
        "details"
    ];
}
