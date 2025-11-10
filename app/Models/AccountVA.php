<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'accounts';


    public function order_log(){
        return $this->morphMany('App\Models\OrderLogVA', 'responsable', '_type', '_responsable', 'id');
    }
}
