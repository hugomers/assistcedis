<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'orders';
    protected $fillable = ['num_ticket', 'name', 'printed', '_created_by', '_workpoint_from', 'time_life', '_status', '_client', '_price_list', '_order'];

    // public function workpoint(){
    //     return $this->belongsTo('App\Models\WorkPointVA', '_workpoint_from');
    // }
    public function from(){
        return $this->belongsTo('App\Models\WorkpointVA', '_workpoint_from');
    }



    public function history(){
        return $this->belongsToMany('App\Models\OrderProcessVA', 'order_log', '_order', '_status')
        ->using('App\Models\OrderLogVA')
        ->withPivot('_responsable', '_type', 'details', 'created_at');
    }

    // public function history(){
    //     return $this->belongsToMany('App\Models\OrderProcessVA', 'order_log', '_order', '_status')
    //         ->withPivot('_responsable', '_type', 'details', 'created_at');
    // }

    public function status(){
        return $this->belongsTo('App\Models\OrderProcessVA', '_status');
    }

    public function client(){
        return $this->belongsTo('App\Models\ClientVA', '_client');
    }

    public function price_list(){
        return $this->belongsTo('App\Models\PriceListVA', '_price_list');
    }

    public function created_by(){
        return $this->belongsTo('App\Models\AccountVA', '_created_by');
    }

    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'product_ordered', '_order', '_product')
                    ->using('App\Models\ProductOrderedPivot')
                    ->withPivot('kit', 'units', 'price', '_price_list', "comments", "total", "amount", '_supply_by', 'toDelivered', 'ipack', 'amountDelivered');
    }
    public function getStaff(){
        return \App\Models\Staff::where('id_va', $this->_created_by)->first();
    }
}
