<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditPaymnets extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'credit_payment';

    public function counterpart(){
        return $this->hasOne('App\Models\CounterPart', "id", "_counterpart");
    }
}

