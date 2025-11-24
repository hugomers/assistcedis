<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegisterVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'cash_registers';
    // protected $fillable = ['serie', 'code', 'ref', '_provider','description','total','created_at'];
    public $timestamps = false;

    public function workpoint(){
        return $this->belongsTo('App\Models\WorkPointVA', '_workpoint');
    }

    public function order_log(){
        return $this->morphMany('App\Models\OrderLogVA', 'responsable', '_type', '_responsable', 'id');
    }
    public function status(){
        return $this->belongsTo('App\Models\CashRegisterStatusVA', '_status');
    }
    public function cashier(){
        return $this->belongsTo('App\Models\AccountVA', '_account');
    }


}
