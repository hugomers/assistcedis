<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OuputInternal extends Model
{

    protected $table = 'output_internals';
    protected $fillable = [
        '_created_by',
        '_updated_by',
        '_state',
        '_warehouse',
        'created_at',
        'updated_at',
        'notes',
        'cod_fs',
    ];


    public function warehouse(){
        return $this->belongsTo('App\Models\Warehouses','_warehouse');
    }
    public function createdby(){
        return $this->belongsTo('App\Models\User','_created_by');
    }
    public function modifyby(){
        return $this->belongsTo('App\Models\User','_updated_by');
    }

    public function bodie(){
        return $this->belongsToMany('App\Models\ProductVA', 'output_bodies', '_output', '_product')
                    ->withPivot('amount');
    }

    public function state(){
        return $this->belongsTo('App\Models\OutputState','_state');
    }
    public function log(){
        return $this->belongsTo('App\Models\OutputLog','_output');
    }





}
