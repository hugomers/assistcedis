<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $table = "sales";

    public function bodie(){
    return $this->hasMany('App\Models\SaleBodie',  '_sale', 'id');
    }

    public function payments(){
    return $this->hasMany('App\Models\SalePayment',  '_sale', 'id');
    }

    public function staff(){
    return $this->belongsTo('App\Models\Staff',  '_staff');
    }
    public function cashier(){
    return $this->belongsTo('App\Models\CashCashier','_cashier');
    }
    public function pfpa(){
    return $this->belongsTo('App\Models\PaymentMethod','_pfpa');
    }
    public function sfpa(){
    return $this->belongsTo('App\Models\PaymentMethod','_sfpa');
    }
    public function val(){
    return $this->belongsTo('App\Models\PaymentMethod','_val');
    }



}
