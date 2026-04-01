<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorePayment extends Model
{
  protected $table = "store_payments";
    public $timestamps = false;

    public function methods(){
        return $this->hasOne('App\Models\PaymentMethod', 'id','_payment');
    }

}
