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

    public function staff(){
        return $this->belongsTo('App\Models\Staff','_staff');
    }
    public function rol(){
        return $this->belongsTo('App\Models\UserRol','_rol');
    }
    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }
    public function stores(){
        return $this->hasMany('App\Models\UserStore','_user','id');
    }

}
