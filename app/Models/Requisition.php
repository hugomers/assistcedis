<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requisition extends Model
{
    protected $table = 'requisition';

    public function stores(){
        return $this->belongsTo('App\Models\Stores', '_stores', 'id');
    }
    public function status(){
        return $this->belongsTo('App\Models\RequisitionState', '_status', 'id');
    }
    public function user(){
        return $this->belongsTo('App\Models\Staff', '_user', 'id');
    }

    public function bodie(){
        return $this->hasMany('App\Models\RequisitionBodies','_requisition');
    }

}
