<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $table = 'deposits';

    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }
    public function status(){
        return $this->belongsTo('App\Models\DepositState','_status');
    }
}
