<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouses extends Model
{
    protected $table = "warehouses";
    public $timestamps = false;

    public function store() {
    return $this->belongsTo('App\Models\Stores', '_store');
    }
    public function type() {
    return $this->belongsTo('App\Models\WarehouseType', '_type');
    }
    public function state() {
    return $this->belongsTo('App\Models\WarehouseState', '_state');
    }
    public function sections(){
        return $this->hasMany('App\Models\CellerSectionVA','_warehouse','id');
    }
}
