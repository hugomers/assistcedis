<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $table = "withdrawals";

    public function provider() {
    return $this->belongsTo('App\Models\ProviderWithdrawal', '_providers', 'id');
    }
    public function cashier(){
    return $this->belongsTo('App\Models\CashCashier','_cashier');
    }

}
