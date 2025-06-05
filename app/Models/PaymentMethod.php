<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = "payment_methods";
    public $timestamps = false;

        public function sales(){
        return $this->hasMany('App\Models\SalePayment', '_payment', 'id');
    }

}
