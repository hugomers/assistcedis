<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStockVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'product_stock';
    // protected $fillable = ['_product', 'amount', 'price', 'total'];
    public $timestamps = false;

}
