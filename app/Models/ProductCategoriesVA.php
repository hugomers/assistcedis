<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategoriesVA extends Model{

    // protected $connection = 'vizapi';
    protected $table = 'product_categories';
    protected $fillable = ['name', 'code', 'deep', '_root','alias','num','prefix'];
    protected $hidden = ['attributes'];
    public $timestamps = false;

    /*****************
     * Relationships *
     *****************/
    public function products(){
        return $this->hasMany('App\Models\ProductVA', '_category', 'id');
    }

    public function attributes(){
        return $this->hasMany('App\Models\CategoryAttribute', '_category', 'id');
    }
    public function category(){//se quitan si hay problema va
        return $this->belongsTo('\App\Models\ProductCategoriesVA');
    }

    public function familia()//se quitan si hay problema va
    {
        return $this->belongsTo(ProductCategoriesVA::class, '_root');
    }

    public function seccion()//se quitan si hay problema va
    {
        return $this->belongsTo(ProductCategoriesVA::class, '_root');
    }
}
