<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditInvoice extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'credit_invoices';



    public function tickets(){
        return $this->hasMany('App\Models\CreditTicket', "_credit");
    }

    public function payments(){
        return $this->hasMany('App\Models\CreditPaymnets', "_credit");
    }
}
