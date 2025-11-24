<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegisterStatusVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'cash_status';
    protected $fillable = ['name'];
    public $timestamps = false;



}
