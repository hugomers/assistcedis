<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashCashier extends Model
{
    protected $table = "cash_cashiers";
    public $timestamps = false;



    public function user(){
        return $this->belongsTo('App\Models\User','_user');
    }
    public function print(){
        return $this->belongsTo('App\Models\Printer','_tck_print');
    }
    public function cash(){
        return $this->belongsTo('App\Models\CashRegister','_cash');
    }
    public function withdrawal(){
        return $this->hasMany('App\Models\Withdrawal','_cashier');
    }
    public function ingress(){
        return $this->hasMany('App\Models\Ingress','_cashier');
    }
    public function addvances(){
        return $this->hasMany('App\Models\Advances','_cashier');
    }
    public function sale(){
        return $this->hasMany('App\Models\Sale','_cashier');
    }




}
