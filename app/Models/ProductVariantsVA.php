<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantsVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'product_variants';

}
