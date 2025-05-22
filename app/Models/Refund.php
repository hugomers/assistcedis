<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = "refunds";

    public function storefrom(){
        return $this->belongsTo('App\Models\Stores', '_store_from', 'id');
    }
    public function storeto(){
        return $this->belongsTo('App\Models\Stores', '_store_to', 'id');
    }
    public function status(){
        return $this->belongsTo('App\Models\RefundState', '_status', 'id');
    }
    public function type(){
        return $this->belongsTo('App\Models\RefundType', '_type', 'id');
    }
    public function createdby(){
        return $this->belongsTo('App\Models\Staff', '_created_by', 'id');
    }
    public function receiptby(){
        return $this->belongsTo('App\Models\Staff', '_receipt_by', 'id');
    }
    public function bodie(){return $this->hasMany('\App\Models\RefundBodie','_refund','id'); }

}
