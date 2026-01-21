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

    public function parent(){
        return $this->belongsTo(ModulesApp::class, 'root', 'id');
    }

    public function children(){
        return $this->hasMany(ModulesApp::class, 'root', 'id');
    }

    public function roles(){
        return $this->belongsToMany(
            UserRol::class,
            'rol_modules',
            '_module',
            '_rol'
        );
    }
}
