<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class InvocidReceivedVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'invoices_received';
    protected $fillable = ['serie', 'code', 'ref', '_provider','description','total','created_at'];
    public $timestamps = false;

    public function products(){
        return $this->belongsToMany('App\Models\ProductVA', 'product_received', '_order', '_product')
        ->withPivot('amount', 'price', 'total');
    }

    public function scopeSumAmountByYear($query)
{
    return $query->selectRaw('YEAR(invoices_received.created_at) as year, SUM(PR.amount) as total_amount')
        ->join('product_received AS PR', 'invoices_received.id', '=', 'PR._order')
        ->groupBy(DB::raw('YEAR(invoices_received.created_at), PR._product'))
        ->orderBy('year');
}

}
