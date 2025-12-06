<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CycleCountBodyVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'cyclecount_body';

    public function cyclecount(){
        return $this->belongsTo('App\Models\CycleCountVA', '_cyclecount');
    }


}
