<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'requisition';
    protected $fillable = ['name', 'num_ticket', 'num_ticket_store', 'notes', '_created_by', '_workpoint_from', '_workpoint_to', '_type', '_status', 'printed', 'time_life'];


    // public function createdby(){
    //     return $this->belongsTo('App\Models\User', '_created_by', 'id');
    // }
    // public function store(){
    //     return $this->belongsTo('App\Models\Stores', '_store', 'id');
    // }
    // public function warehouse(){
    //     return $this->belongsTo('App\Models\Warehouses', '_warehouse', 'id');
    // }
    // public function bodie(){return $this->hasMany('\App\Models\InvoiceBodies','_invoice','id'); }

    public function type(){
        return $this->belongsTo('App\Models\InvoiceType', '_type');
    }

    public function status(){
        return $this->belongsTo('App\Models\InvoiceStatus', '_status');
    }

    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'product_required', '_requisition', '_product')
                    ->withPivot('amount', '_supply_by', 'units', 'cost', 'total', 'comments', 'stock', 'toDelivered', 'toReceived', 'ipack', 'checkout','_suplier_id');
    }

    public function to(){
        return $this->belongsTo('App\Models\WorkPointVA', '_workpoint_to');
    }

    public function from(){
        return $this->belongsTo('App\Models\WorkPointVA', '_workpoint_from');
    }

    public function created_by(){
        return $this->belongsTo('App\Models\AccountVA', '_created_by');
    }

    public function log(){
        return $this->belongsToMany('App\Models\InvoiceStatus', 'requisition_log', '_order', '_status')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }

    public function partition(){ return $this->hasMany('App\Models\partitionRequisition','_requisition');}


}
