<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\StoresEva;
use App\Models\StoresTemplateEva;
use App\Models\WorkpointVA;
use App\Models\ProductVA;
use App\Models\Zone;
use App\Models\ZoneStore;
use App\Models\Position;
use App\Models\Quiz;
use App\Models\Restock;
use App\Models\partitionRequisition;
use App\Models\SalesVA;
use Carbon\Carbon;
use App\Models\Opening;
use App\Models\OpeningType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class OperationController extends Controller
{
    public function index(Request $request){
        $stores = null;
        $zoneStore = Zone::with('stores')->get();
        return response()->json($zoneStore);
    }


    public function getSalesMonth(Request $request){
        $month = $request->_month;

        if($request->zone == "all"){
            $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22])->get();
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore)->get();
        }

        $workpoints = $stores->pluck('id_viz');
        $increments = $stores->pluck('increment','id_viz');

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

        // $sales->transform(function ($s) {

        //     $s->growth = $s->last_total > 0
        //         ? (($s->current_total - $s->last_total) / $s->last_total) * 100
        //         : 100;

        //     $s->ticket_avg_current = $s->tickets > 0
        //         ? $s->current_total / $s->tickets
        //         : 0;

        //     $s->ticket_avg_last = $s->last_tickets > 0
        //         ? $s->last_total / $s->last_tickets
        //         : 0;

        //     return $s;
        // });
        $sales->transform(function ($s) use ($increments) {

        $increment = $increments[$s->id_store] ?? 1;

        $s->last_total   = round($s->last_total * $increment,2);
        $s->last_tickets = round($s->last_tickets * $increment,0);

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
                // $response = Http::timeout(5)->post("http://192.168.10.160:1619/storetools/public/api/reports/getCutsReport",["_month"=>$month]);
                if ($response->ok()) {
                    $data = $response->json();
                    $workpoint->cuts = $data;
                } else {
                    $workpoint->cuts = [];
                }
            } catch (\Exception $e) {
                $workpoint->cuts = [];
            }
        }

        return response()->json($workpoints);
    }

    public function getStatusInventory(Request $request){
         $month = $request->_month;
        if($request->zone == "all"){
            $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22])->get();
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore)->get();
        }

        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();

        $workpoints = WorkpointVA::whereIn('id',$stores->pluck('id_viz'))
        ->with(['cyclecounts' => function($q) use($from,$to){
            $q->where('_status',3)
            ->whereBetween('created_at',[$from,$to])
            ->with('products');
        }])->get();

        $report = $workpoints->map(function($wp){

            $warehouses = $wp->cyclecounts->groupBy(function($cc){

                $settings = is_array($cc->settings)
                    ? $cc->settings
                    : json_decode($cc->settings,true);

                return $settings['warehouse']['id'] ?? 'UNK';

            });

            $cyclecounts = $warehouses->map(function($cycles,$warehouse){
                $products = $cycles->flatMap(function($c){

                    return $c->products->map(function($p) use ($c){

                        $p->cycle_date = $c->created_at;

                        return $p;

                    });

                });
                $latestProducts = $products
                    ->sortByDesc('cycle_date')
                    ->unique('id')
                    ->values();

                $total = $latestProducts->count();

                $correct = $latestProducts->filter(function($p){
                    return $p->pivot->stock_acc == $p->pivot->stock_end;
                })->count();
                $incorrect = $latestProducts->filter(function($p){
                    return $p->pivot->stock_acc != $p->pivot->stock_end;
                })->count();
                // $precisionTotal = 0;
                // $precisionCount = 0;

                // foreach($latestProducts as $p){

                //     $acc = $p->pivot->stock_acc;
                //     $end = $p->pivot->stock_end;

                //     if($end > 0){

                //         $precision = 1 - (abs($acc - $end) / $end);

                //         $precisionTotal += $precision;
                //         $precisionCount++;

                //     }

                // }

                // $precision = $precisionCount > 0
                //     ? round(($precisionTotal / $precisionCount) * 100,2)
                //     : 0;

                return [
                    'id'=>$warehouse,
                    'count'=>$cycles->count(),
                    'products'=>$total,
                    'correctos'=>$correct,
                    'incorrect'=>$incorrect,
                    'accuracy'=>$total > 0
                        ? round(($correct/$total)*100,2)
                        : 0,
                    'diff'=>$total > 0
                        ? round(($incorrect/$total)*100,2)
                        : 0,
                    // 'precision'=>$precision
                ];

            })->values();

            return [
                'id'=>$wp->id,
                'name'=>$wp->name,
                'cyclecounts'=>$cyclecounts
            ];

        });

        return response()->json($report,200);
    }

    public function getStatusPerson(Request $request){
        $month = $request->_month;
        if($request->zone == "all"){
            $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22])->get();
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore)->get();
        }
        $workpoints = $stores->pluck('id_eva');
        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();

        $evastore = StoresEva::with(['template'])
        ->withCount([
            'users as plantilla' => function ($q) {$q->where('_state','!=',4);} ,
            'users as bajas'=> function ($q) use($from,$to) {$q->where('_state',4)->whereBetween('updated_at',[$from,$to]);} ])
        ->whereIn('id',$workpoints)->get();
        return response()->json($evastore);
    }
    // public function getSatisfactionClient(Request $request){
    //     $month = $request->_month;
    //     if($request->zone == "all"){
    //         $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22]);
    //     }else{
    //         $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
    //         $stores = Stores::whereIn('id',$zoneStore);
    //     }
    //     $workpoints = $stores->pluck('id');
    //     $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
    //     $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();

    //     $quiz = $stores->with(['quiz' => function($q) use($from,$to) {$q->whereBetween('created_at',[[$from,$to]]);}])->get();

    //     return response()->json($quiz);
    // }
    public function getSatisfactionClient(Request $request){
       $month = $request->_month;
        if($request->zone == "all"){
            $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22]);
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore);
        }
        $workpoints = $stores->pluck('id');
        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();
        $stores = $stores->with(['quiz' => function($q) use ($from, $to) {
            $q->whereBetween('created_at', [$from, $to]);
        }])->get();
        $scoreFields = [
            'first','second','third','fourth','fifth','sixth','seventh'
        ];
        $stores = $stores->map(function($store) use ($scoreFields){
            $quiz = $store->quiz;
            $total = $quiz->count();
            if ($total == 0) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'total' => 0,
                    'average' => 0,
                    'recommend_si' => 0
                ];
            }
            $average = $quiz->avg(function($q) use ($scoreFields){
                return collect($scoreFields)->avg(fn($f)=>$q->$f);
            });
            $si = $quiz->where('eightth','Si')->count();
            return [
                'id' => $store->id,
                'name' => $store->name,
                'total' => $total,
                'average' => round($average,2),
                'recommend_si' => round(($si/$total)*100)
            ];
        });
        return response()->json($stores);
    }

    public function statusAdm(Request $request){
        $month = $request->_month;
        if($request->zone == "all"){
            $stores = Stores::where([['_active',1]])->WhereNotIn('id',[1,2,21,22])->get();
        }else{
            $zoneStore = ZoneStore::where('zone_id',$request->zone)->pluck('store_id');
            $stores = Stores::whereIn('id',$zoneStore)->get();
        }
        $workpoints = $stores->pluck('id_eva');
        $from = Carbon::create(now()->year, $month, 1)->startOfMonth();
        $to   = Carbon::create(now()->year, $month, 1)->endOfMonth();
        $report = DB::connection('eva')->select("CALL ReportOperation(?, ?)", [ $from,  $to]);
        $res = collect($report)->whereIn('IDSUCURSAL', $workpoints);

        return $res->values();
    }

}
