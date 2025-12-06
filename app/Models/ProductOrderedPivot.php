<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductOrderedPivot extends Pivot
{
    protected $connection = 'vizapi';
    protected $table = "product_ordered";

   public function supplyBy()
   {
       return $this->belongsTo('App\Models\ProductUnitVA', '_supply_by');
   }
}
