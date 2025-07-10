<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'products';

    public function invoices(){
        return $this->belongsToMany('App\Models\Invoice', 'product_required', '_product', '_requisition')
                    ->withPivot('units', 'comments', 'stock');
    }
    public function stocks(){
        return $this->belongsToMany('App\Models\WorkpointVA', 'product_stock', '_product', '_workpoint')
                    ->withPivot('min', 'max', 'stock', 'gen', 'exh', 'des', 'fdt', 'V23', 'LRY', 'in_transit', '_status');
    }
    public function prices(){
        return $this->belongsToMany('App\Models\PriceListVA', 'product_prices', '_product', '_type')
                    ->withPivot(['price']);
    }
    public function variants(){
        return $this->hasMany('App\Models\ProductVariantsVA', '_product', 'id');
    }
    public function locations(){
        return $this->belongsToMany('App\Models\CellerSectionVA', 'product_location', '_product', '_location');
    }
    public function units(){
        return $this->belongsTo('App\Models\ProductUnitVA', '_unit');
    }
    public function category(){
        return $this->belongsTo('App\Models\ProductCategoriesVA', '_category');
    }

}
