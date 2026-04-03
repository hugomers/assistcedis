<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductStockVAPvt extends Pivot

{
    protected $table = 'product_stock';
    public $timestamps = false;
    protected $fillable = ['_status'];
    protected $appends = ['state_data'];

    public function state(){
        return $this->belongsTo('App\Models\ProductStatusVA', '_status');
    }
    public function store(){
        return $this->belongsTo('App\Models\WorkpointVA', '_workpoint');
    }
    public function getStateDataAttribute(){
        return $this->state()->first();
    }
}
