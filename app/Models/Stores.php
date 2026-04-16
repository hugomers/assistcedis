<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stores extends Model
{
    // use HasFactory;
    protected $table = 'stores';

    public function Solicitudes(){
        return $this->hasMany('App\Models\Solicitudes','_store','id');
    }
    public function requisition(){
        return $this->hasMany('App\Models\Requisition','_stores');
    }
    public function sale(){
        return $this->hasMany('App\Models\Sale','_store');
    }
    public function opens(){
        return $this->hasMany('App\Models\Opening','_store');
    }
    public function cashs(){
        return $this->hasMany('App\Models\CashRegister','_store');
    }
    public function quiz(){
        return $this->hasMany('App\Models\Quiz','_store');
    }
    public function methods(){
        return $this->belongsToMany('App\Models\PaymentMethod', 'store_payments', '_store', '_payment');
    }
    public function cashers(){
        return $this->hasManyThrough(
            CashCashier::class,
            CashRegister::class,
            '_store',
            '_cash',
            'id',
            'id'
        );
    }

}
