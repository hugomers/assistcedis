<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRol extends Model
{
    protected $table = "user_roles";
    public $timestamps = false;
    protected $fillable = [
        'name',
        'alias',
        '_area',
        '_type',
        'deep',
    ];

    // public function modules(){
    //     return $this->hasMany('App\Models\RolModule','_rol','id');
    // }
    public function type(){
        return $this->belongsTo('App\Models\TypeRol','_type');
    }

    public function modules(){
        return $this->belongsToMany(
            'App\Models\ModulesApp',
            'rol_modules',
            '_rol',
            '_module'
        );
    }
}
