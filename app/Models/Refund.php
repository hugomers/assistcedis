<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = "transfer_bw_stores";
    protected $fillable = [
        '_origin',
        '_destiny',
        '_type',
        '_state',
        '_created_by',
        '_receipt_by',
        'notes',
        'refund',
        'season_ticket',
        'invoice',
        'entry',
        'date_receipt'
    ];


    public function origin(){
        return $this->belongsTo('App\Models\Warehouses', '_origin', 'id');
    }
    public function destiny(){
        return $this->belongsTo('App\Models\Warehouses', '_destiny', 'id');
    }
    public function status(){
        return $this->belongsTo('App\Models\RefundState', '_state', 'id');
    }
    public function type(){
        return $this->belongsTo('App\Models\RefundType', '_type', 'id');
    }
    public function createdby(){
        return $this->belongsTo('App\Models\User', '_created_by', 'id');
    }
    public function receiptby(){
        return $this->belongsTo('App\Models\User', '_receipt_by', 'id');
    }
    public function bodie(){
        return $this->belongsToMany('App\Models\ProductVA', 'transfer_bw_store_bodies', '_transfer', '_product')
                    ->withPivot('to_delivered','to_received');
    }

}
