<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\ProductVA;
use App\Models\Zone;
use App\Models\ZoneStore;
use App\Models\Staff;
use App\Models\Position;
use App\Models\Restock;
use App\Models\partitionRequisition;
use App\Models\SalesVA;
use Carbon\Carbon;
use App\Models\Opening;
use App\Models\OpeningType;
use Illuminate\Support\Facades\Http;


class OperationController extends Controller
{
    public function index(Request $request){
        $stores = null;
        $sales = null;
        if($request->zone == "all"){
            $stores = Stores::where('_active',1)->get();
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->get()->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore)->get();
        }
        $sales = $this->getSales($stores,$request->_month);
        return $sales;
    }

    private function getSales($stores,$month){
        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();
        $lastYearFrom = $from->copy()->subYear();
        $lastYearTo   = $to->copy()->subYear();
        $workpoints = $stores->pluck('id_viz');
        // foreach($stores as &$store){
            $sales = SalesVA::with([
                'cashRegister' => function($q) use ($workpoints)  {$q->whereIn('_workpoint',$workpoints);},
                'products.providers',
                'products.makers',
                'products.category.familia.seccion',
                'client'
            ])
            ->whereHas('cashRegister', function($q) use ($workpoints)  {$q->whereIn('_workpoint',$workpoints);})
            ->whereBetween('created_at',[$from,$to])
            ->get();
            $staffIds = $sales->pluck('_seller')->unique();
            $staff = Staff::whereIn('id_tpv', $staffIds)
            ->get()
            ->keyBy('id_tpv');
            $sales->transform(function ($sale) use ($staff) {
                $sale->staff = $staff[$sale->_seller] ?? null;
                return $sale;
            });

           $lastSales =   SalesVA::with([
                'cashRegister' => function($q) use ($workpoints)  {$q->whereIn('_workpoint',$workpoints);},
                'products.providers',
                'products.makers',
                'products.category.familia.seccion',
                'client'
            ])
            ->whereHas('cashRegister', function($q) use ($workpoints)  {$q->whereIn('_workpoint',$workpoints);})
            ->whereBetween('created_at',[$lastYearFrom,$lastYearTo])
            ->get();
            $res = [
            "current_sales"=>$sales,
            "last_sales"=>$lastSales,
            ];
            return $res;
    }

    public function getSalesMonth(Request $request){
        $month = $request->_month;

        if($request->zone == "all"){
            $stores = Stores::where([['_active',1],['id','!=',1]])->get();
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore)->get();
        }

        $workpoints = $stores->pluck('id_viz');

        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();

        $lastYearFrom = $from->copy()->subYear();
        $lastYearTo   = $to->copy()->subYear();

        $sales = SalesVA::selectRaw('
            workpoints.name as store,
            workpoints.id as id_store,
            SUM(CASE
                WHEN sales.created_at BETWEEN ? AND ?
                THEN sales.total
            END) as current_total,

            SUM(CASE
                WHEN sales.created_at BETWEEN ? AND ?
                THEN sales.total
            END) as last_total,

            COUNT(CASE
                WHEN sales.created_at BETWEEN ? AND ?
                THEN sales.id
            END) as tickets,

            COUNT(CASE
                WHEN sales.created_at BETWEEN ? AND ?
                THEN sales.id
            END) as last_tickets
        ',[
            $from,$to,
            $lastYearFrom,$lastYearTo,
            $from,$to,
            $lastYearFrom,$lastYearTo
        ])
        ->join('cash_registers','cash_registers.id','=','sales._cash')
        ->join('workpoints','workpoints.id','=','cash_registers._workpoint')
        ->whereIn('cash_registers._workpoint',$workpoints)
        ->whereBetween('sales.created_at',[$lastYearFrom,$to])
        ->groupBy('cash_registers._workpoint')
        ->get();

        $sales->transform(function ($s) {

            $s->growth = $s->last_total > 0
                ? (($s->current_total - $s->last_total) / $s->last_total) * 100
                : 100;

            $s->ticket_avg_current = $s->tickets > 0
                ? $s->current_total / $s->tickets
                : 0;

            $s->ticket_avg_last = $s->last_tickets > 0
                ? $s->last_total / $s->last_tickets
                : 0;

            return $s;
        });

        return response()->json($sales,200);
    }

    public function getCashStatus(Request $request){// necesito los descuadres y las aperturas de caja
        $month = $request->_month;

        if($request->zone == "all"){
            $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22]);
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore);
        }
        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();
        $posdat = [
            "from"=>$from,
            "to"=>$to
        ];

        $workpoints = $stores
        ->withCount([
            'opens' => function ($q) use ($posdat) {$q->whereBetween('created_at',[$posdat['from'],$posdat['to']]);},
            'cashers' => function ($q) use ($posdat) {$q->whereBetween('open_date',[$posdat['from'],$posdat['to']]);}
        ])
        ->get();
        foreach($workpoints as &$workpoint){
            try {
                $response = Http::timeout(5)->post("http://{$workpoint->ip_address}/storetools/public/api/reports/getCutsReport",["_month"=>$month]);
                if ($response->ok()) {
                    $data = $response->json();
                    $workpoint->descuadre = floatval($data['DESCUADRE']);
                } else {
                    $workpoint->descuadre = 0;
                }
            } catch (\Exception $e) {
                $workpoint->descuadre = 0;
            }
        }

        return response()->json($workpoints);
    }

    public function getStatusInventory(Request $request){


    }

}
