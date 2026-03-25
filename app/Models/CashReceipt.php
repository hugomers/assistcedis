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
        "withdrawal",
        "discrepancy",
        "open_date",
        "details",
        "mismatch_observation",
        "card_receipt",
        "card_send",
        "card_discrepancy",
        "card_observation"
    ];
}
