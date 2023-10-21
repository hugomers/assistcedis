<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitudes extends Model
{
    // use HasFactory;
    protected $table = 'forms';

    public function stores(){
        return $this->belongsTo('App\Models\Stores','_store');
    }

}
