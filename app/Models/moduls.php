<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class moduls extends Model
{
    protected $table = 'moduls';

    public function modules(){
        return $this->hasMany('App\Models\ModulesApp','modul','id');
    }


}
