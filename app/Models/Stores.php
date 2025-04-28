<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stores extends Model
{
    // use HasFactory;
    protected $table = 'stores';

    public function Solicitudes(){
        return $this->hasMany('App\Models\Solicitudes','_store','id');
    }

    public function Staff(){
        return $this->hasMany('App\Models\Staff','_store','id');
    }
    public function requisition(){
        return $this->hasMany('App\Models\Requisition','_stores');
    }

}
