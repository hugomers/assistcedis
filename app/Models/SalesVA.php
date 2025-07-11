<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class SalesVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'sales';
    // protected $fillable = ['serie', 'code', 'ref', '_provider','description','total','created_at'];
    public $timestamps = false;

    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'product_sold', '_order', '_product')
        ->withPivot('amount', 'price', 'total');
    }

    public function scopeSumAmountByYear($query)
{
    return $query->selectRaw('YEAR(sales.created_at) as year, SUM(PS.amount) as total_amount')
        ->join('product_sold as PS', 'sales.id', '=', 'PS._sale')
        ->groupBy(DB::raw('YEAR(sales.created_at), PS._product'));
        // ->orderBy('year');
}

}
