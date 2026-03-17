<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $table = 'zones';

    // public function stores(){
    //     return $this->hasMany('App\Models\ZoneStore','zone_id','id');
    // }
    public function stores(){
        return $this->belongsToMany('App\Models\Stores', 'zone_stores', 'zone_id','store_id');
    }
}
