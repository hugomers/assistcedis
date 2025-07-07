<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkpointVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'workpoints';


    public function printers(){
        return $this->hasMany('App\Models\PrinterVA', '_workpoint');
    }
}
