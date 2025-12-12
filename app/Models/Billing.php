<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    protected $table = "billings";
    protected $fillable = [
            "_store",
            "ticket",
            "total",
            "_state",
            "_cfdi",
            "notes",
            "name",
            "email",
            "celphone",
            "rfc",
            "razon_social",
            "address",
    ];
    // public function log(){
    //     return $this->belongsToMany('App\Models\BillingStates', 'billing_logs', '_billing', '_state')
    //     ->withPivot('details', 'created_at','_user');
    // }

    public function logs(){
    return $this->hasMany('App\Models\BillingLogs', '_billing');
    }

    public function payments(){
        return $this->hasMany('App\Models\BillingPayments', '_billing');
    }
    public function status(){
        return $this->belongsTo('App\Models\BillingStates', '_state');
    }
    public function store(){
        return $this->belongsTo('App\Models\Stores', '_store');
    }
    public function cfdi(){
        return $this->belongsTo('App\Models\UseCfdi', '_cfdi');
    }

}
