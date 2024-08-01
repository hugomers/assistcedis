<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistDevice extends Model
{
    protected $table = "assist_devices";
    public $timestamps = false;


    public function store(){ return $this->hasOne('App\Models\Stores','id','_store'); }
}