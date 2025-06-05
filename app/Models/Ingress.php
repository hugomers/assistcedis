<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingress extends Model
{
    protected $table = "ingress";
    public function client() {
    return $this->belongsTo('App\Models\IngressClient', '_client', 'id');
    }
    public function cashier(){
    return $this->belongsTo('App\Models\CashCashier','_cashier');
    }
}
