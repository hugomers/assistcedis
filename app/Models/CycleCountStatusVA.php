<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountStatusVA extends Model
{
protected $connection = 'vizapi';
  protected $table = 'cyclecount_status';
  protected $fillable = ['name'];


  public function cyclecounts(){
    return $this->hasMany('App\Models\CycleCount', '_status', 'id');
  }
}
