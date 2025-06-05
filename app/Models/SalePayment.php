<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    protected $table = "sale_payments";

    public function payment() {
    return $this->belongsTo('App\Models\PaymentMethod', '_payment', 'id');
    }
    public function sale() {
    return $this->belongsTo('App\Models\Sale', '_sale', 'id');
    }
}


