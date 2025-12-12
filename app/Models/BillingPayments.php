<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingPayments extends Model
{
    protected $table = "billing_payments";
    public $timestamps = false;

    protected $fillable = [
        "payment",
        "concept",
        "import",
        "type_card"
    ];
}
