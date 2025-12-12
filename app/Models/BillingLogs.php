<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingLogs extends Model
{
    protected $table = "billing_logs";
        protected $fillable = [
            "_state",
            '_user',
            'details',
            'created_at'
    ];

        public function user(){
        return $this->belongsTo('App\Models\User', '_user');
    }
}
