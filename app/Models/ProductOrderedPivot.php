<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductOrderedPivot extends Pivot
{
    // protected $connection = 'vizapi';
    protected $table = "product_ordered";

    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable  = ['kit', 'units', 'price', '_price_list', "comments", "total", "amount", '_supply_by', 'toDelivered', 'ipack', 'amountDelivered'];

   public function supplyBy()
   {
       return $this->belongsTo('App\Models\ProductUnitVA', '_supply_by');
   }
}
