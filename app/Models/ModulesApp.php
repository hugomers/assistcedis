<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulesApp extends Model
{
    protected $table = "modules_app";


     public function _modul()
    {
        return $this->belongsTo(moduls::class, 'modul', 'id');
    }

    public function rolModules()
    {
        return $this->hasMany(RolModule::class, '_modules', 'id');
    }

}
