<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfers extends Model
{
    protected $table = "transfer_bw_warehouse";
    protected $fillable = [
        'fs_id',
        '_origin',
        '_destiny',
        '_created_by',
        '_updated_by',
        'notes',
        '_state'
    ];

    // public function store(){
    //     return $this->belongsTo('App\Models\Stores','_store');
    // }

    public function origin(){
        return $this->belongsTo('App\Models\Warehouses','_origin');
    }

    public function destiny(){
        return $this->belongsTo('App\Models\Warehouses','_destiny');
    }
    public function created_by(){
        return $this->belongsTo('App\Models\User','_created_by');
    }
    public function modify_by(){
        return $this->belongsTo('App\Models\User','_modify_by');
    }
    // public function bodie(){return $this->hasMany('\App\Models\TransferBodies','_transfer','id'); }


    public function bodie(){
        return $this->belongsToMany('App\Models\ProductVA', 'transfer_bw_bodies', '_transfer', '_product')
                    ->withPivot('amount');
    }


    public function state(){
        return $this->belongsTo('App\Models\TransferWarehouseState','_state');
    }
    public function log(){
        return $this->belongsTo('App\Models\TransferWarehouseLog','_transfer');
    }

}
