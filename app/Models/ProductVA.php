<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ProductVA extends Model
{
    protected $connection = 'vizapi';
    protected $table = 'products';

    public function invoices(){
        return $this->belongsToMany('App\Models\Invoice', 'product_required', '_product', '_requisition')
                    ->withPivot('units', 'comments', 'stock');
    }
    public function stocks(){
        return $this->belongsToMany('App\Models\WorkpointVA', 'product_stock', '_product', '_workpoint')
                    ->withPivot('min', 'max', 'stock', 'gen', 'exh', 'des', 'fdt', 'V23', 'LRY', 'in_transit', '_status');
    }

    public function salesPerWorkpoints(){
        return $this->belongsToMany('App\Models\WorkpointVA', 'cash_register', '_cash', '_workpoint')
                    ->withPivot('');
    }


    public function prices(){
        return $this->belongsToMany('App\Models\PriceListVA', 'product_prices', '_product', '_type')
                    ->withPivot(['price']);
    }
    public function variants(){
        return $this->hasMany('App\Models\ProductVariantsVA', '_product', 'id');
    }
    public function locations(){
        return $this->belongsToMany('App\Models\CellerSectionVA', 'product_location', '_product', '_location');
    }
    public function units(){
        return $this->belongsTo('App\Models\ProductUnitVA', '_unit');
    }
    public function category(){
        return $this->belongsTo('App\Models\ProductCategoriesVA', '_category');
    }

    public function sales(){
        return $this->belongsToMany('App\Models\SalesVA', 'product_sold', '_product', '_sale')
                    ->withPivot('amount','price','total');
    }
    public function purchases(){
        return $this->belongsToMany('App\Models\InvocidReceivedVA', 'product_received', '_product', '_order')
                    ->withPivot('amount', 'price', 'total');
    }
    public function status(){
        return $this->belongsTo('App\Models\ProductStatusVA', '_status');
    }

    public function salesAmountByYear(){
        return DB::connection('vizapi')->table('sales')
            ->join('product_sold', 'sales.id', '=', 'product_sold._sale')
            ->where('product_sold._product', $this->id)
            ->whereRaw('YEAR(sales.created_at) >= 2020')
            ->selectRaw('YEAR(sales.created_at) as year, SUM(product_sold.amount) as total_amount')
            ->groupBy(DB::raw('YEAR(sales.created_at)'))
            ->orderBy('year')
            ->get();
    }

    public function purchasesAmountByYear(){
        return DB::connection('vizapi')->table('invoices_received')
            ->join('product_received', 'invoices_received.id', '=', 'product_received._order')
            ->where('product_received._product', $this->id)
            ->whereRaw('YEAR(invoices_received.created_at) >= 2020')
            ->selectRaw('YEAR(invoices_received.created_at) as year, SUM(product_received.amount) as total_amount')
            ->groupBy(DB::raw('YEAR(invoices_received.created_at)'))
            ->orderBy('year')
            ->get();
    }

    public function combinedAmountByYear(){
        $sales = $this->salesAmountByYear()
            ->map(fn($r) => ['year' => (int) $r->year, 'sales' => $r->total_amount, 'purchases' => 0]);

        $purchases = $this->purchasesAmountByYear()
            ->map(fn($r) => ['year' =>(int) $r->year, 'sales' => 0, 'purchases' => $r->total_amount]);

        return $sales->concat($purchases)
            ->groupBy('year')
            ->map(fn($group, $year) => [
                'year' =>(int) $year,
                'ventas' => $group->sum('sales'),
                'compras' => $group->sum('purchases')
            ])
            ->sortBy('year')
            ->values();
    }

    public function providers(){
        return $this->belongsTo('App\Models\ProvidersVA', '_provider');
    }
    public function makers(){
        return $this->belongsTo('App\Models\MakersVA', '_maker');
    }

    public function historicPrices(){
        return $this->hasMany('App\Models\HistoryPriceVA', '_product', 'id');
    }

    public function salesByWorkpointArray() {
        $filteredSales = $this->sales->filter(function ($sale) {
            if (!$sale->cash_register || !$sale->cash_register->workpoint) return false;
                $isCurrentYear =   Carbon::parse($sale->created_at)->year === Carbon::now()->year;
                $isWorkpointActive = $sale->cash_register->workpoint->active === 1;
                return $isCurrentYear && $isWorkpointActive;
        });

        return $filteredSales;
        // ->filter(fn($sale) => $sale->cash_register)
        // ->groupBy(fn($sale) => $sale->cash_register->workpoint->id)
        // ->map(function ($sales, $workpointId) {
        //     $workpoint = $sales->first()->cash_register->workpoint;
        //     $totalAmount = $sales->sum(fn($sale) => $sale->pivot->amount);
        //     $totalPrice = $sales->sum(fn($sale) => $sale->pivot->total);

        //     return [
        //         'id' => $workpoint->id,
        //         'name' => $workpoint->name,
        //         'alias' => $workpoint->alias,
        //         'dominio' => $workpoint->dominio,
        //         '_type' => $workpoint->_type,
        //         '_client' => $workpoint->_client,
        //         'active' => $workpoint->active,
        //         '_port' => $workpoint->_port,
        //         'pivot' => [
        //             '_product' => $this->id,
        //             '_workpoint' => $workpoint->id,
        //             'sales_amount' => $totalAmount,
        //             'sales_total' => $totalPrice,
        //         ]
        //     ];
        // })
        // ->values();
    }
}
