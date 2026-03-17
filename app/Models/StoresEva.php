<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoresEva extends Model
{
    protected $connection = 'eva';
    protected $table = 'stores';

    public function template(){
        return $this->hasOne('App\Models\StoresTemplateEva','_store');
    }
    public function users(){
        return $this->hasMany('App\Models\UserEva','_store');
    }
}
