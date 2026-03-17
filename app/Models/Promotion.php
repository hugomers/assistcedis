<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'promotions';

    public function products(){
        return $this->hasMany('App\Models\PromotionProduct', '_promotion', 'id');
    }
}
