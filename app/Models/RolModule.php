<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolModule extends Model
{
    protected $table = "rol_modules";
        public function module(){
        return $this->belongsTo('App\Models\ModulesApp','_module');
    }

}
