<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProcessConfigVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'order_process_config';
    protected $primaryKey = null;
    protected $guarded = [];
    public $timestamps = false;

    public function process(){
        return $this->belongsTo('App\Models\OrderProcessVA', '_process');
    }


}
