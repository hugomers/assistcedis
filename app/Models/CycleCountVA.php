<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'cyclecount';
    protected $fillable = ["notes", '_workpoint', '_created_by', '_type', '_status'];


    public function type(){
        return $this->belongsTo('App\Models\CycleCountTypeVA', '_type');
    }

    public function created_by(){
        return $this->belongsTo('App\Models\AccountVA', '_created_by');
    }

    public function workpoint(){
        return $this->belongsTo('App\Models\WorkPointVA', '_workpoint');
    }

    public function status(){
        return $this->belongsTo('App\Models\CycleCountStatusVA', '_status');
    }

    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'cyclecount_body', '_cyclecount', '_product')
                    ->withPivot(['stock', 'stock_end', 'stock_acc', 'details']);
    }

    public function responsables(){
        return $this->belongsToMany('App\Models\AccountVA', 'cyclecount_responsables', '_cyclecount', '_account');
    }

    public function log(){
        return $this->belongsToMany('App\Models\CycleCountStatusVA', 'cyclecount_log', '_cyclecount', '_status')
                    ->withPivot('details', 'created_at');
    }
}
