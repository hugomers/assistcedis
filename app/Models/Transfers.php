<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfers extends Model
{
    protected $table = "transfer_bw_warehouses";

    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }

    public function origin(){
        return $this->belongsTo('App\Models\Warehouses','_origin');
    }

    public function destiny(){
        return $this->belongsTo('App\Models\Warehouses','_destiny');
    }

    public function bodie(){return $this->hasMany('\App\Models\TransferBodies','_transfer','id'); }


}
