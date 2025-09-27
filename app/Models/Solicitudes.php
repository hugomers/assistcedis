<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitudes extends Model
{
    // use HasFactory;
    protected $table = 'forms';
    protected $fillable = [
        'nom_cli',
        'celphone',
        'mail',
        'tickets',
        '_store',
        'price',
        'notes',
        '_status',
        'street',
        'num_int',
        'num_ext',
        'col',
        'mun',
        'estado',
        'cp',
        'picture',
    ];

    public function stores(){
        return $this->belongsTo('App\Models\Stores','_store');
    }

    public function replyCli(){
        return $this->hasMany('App\Models\ReplyClient','_form','id');
    }

}
