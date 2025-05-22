<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundState extends Model
{
    protected $table = "refund_status";
    public $timestamps = false;

    public function refunds(){return $this->hasMany('\App\Models\Refund','_status','id'); }
}
