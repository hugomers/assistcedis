<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assist extends Model
{
    protected $table = "assists";

    public function user(){ return $this->hasOne('App\Models\User','id','_user'); }
}
