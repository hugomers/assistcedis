<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CellerVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'celler';

    public function sections(){
        return $this->hasMany('App\Models\CellerSectionVA','_celler','id');
    }




}
