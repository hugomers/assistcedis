<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditTicket extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'credit_tickets';

    public function workpoint(){
        return $this->hasOne('App\Models\WorkpointVA', "id", "_workpoint");
    }
}
