<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable  implements JWTSubject
{
    protected $table = "users";
   protected $fillable  = [
                    "nick",
                    "name",
                    "surnames",
                    "dob",
                    "celphone",
                    "email",
                    "gender",
                    "_rol",
                    "_store",
                    "_state",
                    "avatar",
                    "change_password",
                    "password",
                    "_enterprise",
                    "rc_id",

                ];

    // public function staff(){
    //     return $this->belongsTo('App\Models\Staff','_staff');
    // }
    public function getJWTIdentifier(){
        return $this->getKey();
    }
    public function getJWTCustomClaims(){
        return [
            "uid"=> $this->id,
            "name" => $this->name,
            "_store" => $this->_store,
            "rol" => $this->rol->id
        ];
    }
    public function state(){
        return $this->belongsTo('App\Models\UserState','_state');
    }
    public function rol(){
        return $this->belongsTo('App\Models\UserRol','_rol');
    }
    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }
    public function enterprise(){
        return $this->belongsTo('App\Models\Enterprise','_enterprise');
    }
    public function stores(){
        return $this->belongsToMany(
            'App\Models\Stores',
            'user_stores',
            '_user',
            '_store'
        );
    }

    public function media(){
        return $this->hasMany('App\Models\UserMedia','_user','id');
    }

}
