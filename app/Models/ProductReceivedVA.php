<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReceivedVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'product_received';
    protected $fillable = ['_product', 'amount', 'price', 'total'];
    public $timestamps = false;

    public function purchase(){
      return $this->hasOne('App\Models\InvocidReceivedVA', 'id', '_order');
    }

}
