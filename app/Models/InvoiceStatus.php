<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceStatus extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'requisition_process';


    public function requisitions(){
        return $this->hasMany('App\Models\Invoice', '_status', 'id');
    }

    public function historic(){
        return $this->belongsToMany('App\Models\Invoice', 'requisition_log', '_status', '_order')
                    ->withPivot('id', 'details')
                    ->withTimestamps();
    }



}
