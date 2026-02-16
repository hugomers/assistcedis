<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceVA extends Model
{
    protected $table = 'product_prices';
    protected $primaryKey = null;
    public $timestamps = false;
    protected $fillable = [
        '_product',
        '_type',
        '_rate',
        'price'
    ];

}
