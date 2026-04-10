<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRol extends Model
{
    protected $table = "user_roles";

    // public function modules(){
    //     return $this->hasMany('App\Models\RolModule','_rol','id');
    // }

    public function type(){
        return $this->belongsTo('App\Models\TypeRol','_type');
    }
    public function area(){
        return $this->belongsTo('App\Models\Area','_area');
    }

    public function modules(){
        return $this->belongsToMany(
            'App\Models\ModulesApp',
            '_rol_modules',
            '_rol',
            '_modules'
        );
    }
}
