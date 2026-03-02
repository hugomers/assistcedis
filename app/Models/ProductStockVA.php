<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductStockVA extends Pivot

{
    protected $table = 'product_stock';
    public $timestamps = false;
    protected $appends = ['state_data'];

    public function state(){
        return $this->belongsTo('App\Models\ProductStatusVA', '_state');
    }
    public function store(){
        return $this->belongsTo('App\Models\Warehouse', '_warehouse');
    }
    public function getStateDataAttribute(){
        return $this->state()->first();
    }
}
