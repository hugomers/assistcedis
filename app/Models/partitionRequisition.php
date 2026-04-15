<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class partitionRequisition extends Model
{
    protected $connection = 'vizapi';

    protected $table = 'requisition_partitions';
    public $timestamps = false;
    protected $fillable = [
        "_requisition",
        "_suplier_id",
        "_suplier",
        "_status",
        "_out_verified",
        "_warehouse",
        'entry_key',
    ];


    public function logs(){
        return $this->hasMany('App\Models\partitionLog','_status');
    }

    public function status(){
        return $this->belongsTo('App\Models\InvoiceStatus', '_status');
    }
    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'product_required', '_partition', '_product', 'id')
        ->withPivot('amount', '_supply_by', 'units', 'cost', 'total', 'comments', 'stock', 'toDelivered', 'toReceived', 'ipack', 'checkout','_suplier_id');
    }

    public function requisition(){
        return $this->hasOne('App\Models\Invoice','id','_requisition');
    }

    public function log(){
        return $this->belongsToMany('App\Models\InvoiceStatus', 'partition_logs', '_partition', '_status')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }

    public function getOutVerifiedUser(){
        return \App\Models\User::where('id', $this->_out_verified)->first();
    }
    public function getOutDrivingUser(){
        return \App\Models\User::where('id', $this->_driver)->first();
    }
    public function getSupplyUser(){
        return \App\Models\User::where('id', $this->_suplier_id)->first();
    }
    public function getCheckUser(){
        return \App\Models\User::where('id', $this->_in_verified)->first();
    }
}
