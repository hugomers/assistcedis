<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStatusVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'product_status';
    protected $fillable = ['name'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App/Models\Product', '_status', 'id');
    }
}
