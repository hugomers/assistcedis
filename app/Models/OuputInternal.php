<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OuputInternal extends Model
{

    protected $table = 'outputs_internals';

    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }

    public function warehouse(){
        return $this->belongsTo('App\Models\Warehouses','_warehouse');
    }
    public function bodie(){return $this->hasMany('\App\Models\OuputBodie','_output','id'); }
}
