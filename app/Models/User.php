<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $table = "users";
    protected $fillable  = [
                    "nick",
                    "complete_name",
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


    public function rol(){
        return $this->belongsTo('App\Models\UserRol','_rol');
    }
    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }
    // public function stores(){
    //     return $this->hasMany('App\Models\UserStore','_user','id');
    // }
    public function state(){
        return $this->belongsTo('App\Models\UserState','_state');
    }

    public function stores(){
        return $this->belongsToMany(
            'App\Models\Stores',
            'user_stores',
            '_user',
            '_store'
        );
    }


    public function zone(){
        return $this->belongsTo('App\Models\Zone','id','_responsable');
    }
    public function enterprise(){
        return $this->belongsTo('App\Models\Enterprise','_enterprise');
    }
    public function media(){
        return $this->hasMany('App\Models\UserMedia','_user','id');
    }

}
