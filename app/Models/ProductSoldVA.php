<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSoldVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'product_sold';
    // protected $fillable = ['_product', 'amount', 'price', 'total'];
    public $timestamps = false;

    public function sales(){
      return $this->hasOne('App\Models\SalesVA', 'id', '_sale');
    }
}
