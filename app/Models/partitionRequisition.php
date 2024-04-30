<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class partitionRequisition extends Model
{
    protected $connection = 'vizapi';

    protected $table = 'requisition_partitions';


    public function logs(){
        return $this->hasMany('App\Models\partitionLog','_status');
    }


}
