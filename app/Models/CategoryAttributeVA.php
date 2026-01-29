<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryAttributeVA extends Model
{
    protected $table = 'category_attributes';

    public function catalog(){
        return $this->hasMany('App\Models\AttributeCatalogVA', '_attribute', 'id');
    }
}
