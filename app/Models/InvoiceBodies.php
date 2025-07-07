<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceBodies extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'product_required';
    public $timestamps = false;

}
