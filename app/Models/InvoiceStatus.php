<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceStatus extends Model
{
    // protected $connection = 'vizapi';
    protected $table = 'requisition_states';


    public function requisitions(){
        return $this->hasMany('App\Models\Invoice', '_state', 'id');
    }

    public function historic(){
        return $this->belongsToMany('App\Models\Invoice', 'requisition_log', '_state', '_requisition')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }



}
