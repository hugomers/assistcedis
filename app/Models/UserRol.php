<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRol extends Model
{
    protected $table = "user_roles";

    public function modules(){
        return $this->hasMany('App\Models\RolModule','_rol','id');
    }
}
