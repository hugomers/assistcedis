<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceListVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'price_list';
    protected $fillable = ['name', 'short_name'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->belongsToMany('App\Product', 'product_prices', '_type', '_product')
                    ->withPivot(['price']);
    }
}
