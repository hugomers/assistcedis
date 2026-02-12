<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantsVA extends Model
{
    // protected $connection = 'vizapi';
    protected $table = 'product_variants';
    protected $fillable = [
        'code',
        '_product',
        'barcode',
        'cost',
        'pieces',
        '_provider'
    ];
    public $timestamps = false;

    public function providers(){
        return $this->belongsTo('App\Models\ProvidersVA', '_provider');
    }

}
