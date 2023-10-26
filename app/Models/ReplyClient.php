<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplyClient extends Model
{
    // use HasFactory;
    protected $table = 'reply_client';

    public function solicitudes(){
        return $this->belongsTo('App\Models\Solicitudes','_form');
    }

}