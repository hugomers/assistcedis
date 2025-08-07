<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalStore extends Model
{
    protected $table = "withdrawals_store";

    public function store(){
    return $this->belongsTo('App\Models\CashCashier','_store');
    }
}
