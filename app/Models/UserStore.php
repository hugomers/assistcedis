<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStore extends Model
{
    protected $table = "user_stores";

    public function store(){
        return $this->belongsTo('App\Models\Stores','_store');
    }
}
