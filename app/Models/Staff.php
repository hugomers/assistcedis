<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $table = 'staff';
    public $timestamps = false;

    public function stores(){
        return $this->belongsTo('App\Models\Stores','_store');
    }

    public function position(){
        return $this->belongsTo('App\Models\Position','_position');
    }
}
