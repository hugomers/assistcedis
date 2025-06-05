<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $table = "cash_registers";
    public $timestamps = false;

    // public function cashier(){
    //     return $this->hasMany('App\Models\CashCashier','_cash');
    // }
    public function cashier(){
        return $this->belongsTo('App\Models\CashCashier','id','_cash');
    }
    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }
    public function status(){
        return $this->belongsTo('App\Models\CashStatus','_status');
    }
    public function tpv(){
        return $this->belongsTo('App\Models\CashTpv','_tpv');
    }
}
