<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountVA extends Model
{
    // protected $connection = 'vizapi';
    protected $table = 'cyclecounts';
    protected $fillable = ["notes", '_warehouse', '_created_by', '_type', '_state','settings'];


    public function type(){
        return $this->belongsTo('App\Models\CycleCountTypeVA', '_type');
    }

    public function created_by(){
        return $this->belongsTo('App\Models\User', '_created_by');
    }

    public function warehouse(){
        return $this->belongsTo('App\Models\Warehouses', '_warehouse');
    }

    public function state(){
        return $this->belongsTo('App\Models\CycleCountStatusVA', '_state');
    }

    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'cyclecount_bodies', '_cyclecount', '_product')
                    ->withPivot(['stock', 'stock_end', 'stock_acc', 'details']);
    }

    public function responsables(){
        return $this->belongsToMany('App\Models\User', 'cyclecount_responsables', '_cyclecount', '_user');
    }

    public function log(){
        return $this->belongsToMany('App\Models\CycleCountStatusVA', 'cyclecount_logs', '_cyclecount', '_state')
                    ->withPivot('details', 'created_at');
    }
}
