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

    public function cashRegister(){
        return $this->belongsTo('App\Models\CashRegisterVA','_cash');
    }

    public function scopeSumAmountByYear($query)
{
    return $query->selectRaw('YEAR(sales.created_at) as year, SUM(PS.amount) as total_amount')
        ->join('product_sold as PS', 'sales.id', '=', 'PS._sale')
        ->groupBy(DB::raw('YEAR(sales.created_at), PS._product'));
        // ->orderBy('year');
}

}


    // public function sales(){
    //     return $this->belongsToMany('App\Models\SalesVA', 'product_sold', '_product', '_sale')
    //                 ->withPivot('amount','price','total');
    // }
