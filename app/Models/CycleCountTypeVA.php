<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountTypeVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'cyclecount_type';
    protected $fillable = ['name'];
    public $timestamps = false;

    public function cyclecounts(){
        return $this->hasMany('App\Models\CycleCount', '_type', 'id');
    }
}
