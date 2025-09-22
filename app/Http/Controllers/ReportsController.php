<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ProductVA;
use App\Models\ProvidersVA;
use App\Models\MakersVA;
use App\Models\ProductCategoriesVA;
use App\Models\ProductUnitVA;
use App\Models\Stores;
use App\Models\WorkpointVA;
use App\Models\ControlFigures;
use App\Models\historyPricesVA;


class ReportsController extends Controller
{
    public function Index(){
        return ProductCategoriesVA::whereNotNull('alias')->get();
    }
    public function obtReport(Request $request){
        $startDate = null;
        $endDate = null;
        $time = $request->time;//para ventas,
        $range = $request->range;//para ventas de rango de donde a donde
        $filters = $request->filters;//para saber  donde mero
        $workpoint = $request->workpoint;
        if($time['id'] == 1){
            if(isset($range['from'])){
                $startDate = $range['from'];
                $endDate = $range['to'];
            }else{
                $startDate = $range;
                $endDate = $range;
            }
        }
        $products = ProductVA::with([
            'purchases',
            'prices' => fn($q) => $q->where('_type','!=',7)->orderBy('id'),
            'category.familia.seccion',
            'providers',
            'stocks' => fn($q) => $q->where('id',$workpoint['id_viz']),
            'sales' => function($q) use ($time, $startDate, $endDate, $workpoint,$range) {
                switch($time['id']) {
                    case 1:
                        if ($startDate && $endDate) {
                            $q->whereBetween('created_at', [$startDate, $endDate]);
                        }
                        break;

                    case 2:
                        $q->whereDate('created_at', $range);
                        break;

                    case 3:
                        $q->whereRaw('WEEK(created_at,1) = ?', [$range]);
                        break;

                    case 4:
                        $q->whereMonth('created_at', $range)
                        ->whereYear('created_at', date('Y'));
                        break;
                }
                $q->whereHas('cashRegister', fn($q2) =>
                    $q2->where('_workpoint', $workpoint['id_viz'])
                );
            }
        ])
        ->when(isset($filters['categoria']), fn($q) =>
            $q->whereHas('category', fn($q2) =>
                $q2->where('id', $filters['categoria'])
            )
        )
        ->when(isset($filters['familia']), fn($q) =>
            $q->whereHas('category.familia', fn($q2) =>
                $q2->where('id', $filters['familia'])
            )
        )
        ->when(isset($filters['seccion']), fn($q) =>
            $q->whereHas('category.familia.seccion', fn($q2) =>
                $q2->where('id', $filters['seccion'])
            )
        )
        ->whereHas('stocks', fn($q) =>
            $q->where('id', $workpoint['id_viz'])
        )
        ->where('_status', '!=', 4)
        ->get();

        return response()->json($products,200);
    }


    // public function reportWarehouses(Request $request){
    //     $filters = $request->all();
    //     $products = ProductVA::with([
    //         'providers',
    //         'makers',
    //         'category.familia.seccion',
    //         'prices',
    //         'stocks' => fn($q) => $q->where('active',1),
    //         'sales' => fn($q) => fn($q) => $q->whereYear('created_at', now()->year)->orWhereYear('created_at', now()->year - 1),
    //         'purchases'=> fn($q) => $q->whereYear('created_at',now()->year)
    //     ])
    //     ->when(count($filters['categories']) > 0, fn($q) =>
    //         $q->whereHas('category', fn($q2) =>
    //         $q2->whereIn('id', $filters['categories'])))
    //     ->when(count($filters['familys'])> 0, fn($q) =>
    //         $q->whereHas('category.familia', fn($q2) =>
    //         $q2->whereIn('id', $filters['familys'])))
    //     ->when(count($filters['sections'])> 0, fn($q) =>
    //         $q->whereHas('category.familia.seccion', fn($q2) =>
    //         $q2->whereIn('id', $filters['sections'])))
    //     ->where('_status','!=',4)
    //     ->get();

    //   return $products;
    // }


    // public function reportWarehouses(Request $request){

    //     $data = $request->all();
    //     $report = [];
    //     $products = ProductVA::with([
    //         'providers',
    //         'makers',
    //         'category.familia.seccion',
    //         'prices',
    //         'stocks' => fn($q) => $q->where('active',1),
    //         'sales' => fn($q) => $q->whereYear('created_at', now()->year),
    //         'purchases'=> fn($q) => $q->whereYear('created_at',now()->year)
    //     ])
    //     // ->when(count($filters['categories']) > 0, fn($q) =>
    //     //     $q->whereHas('category', fn($q2) =>
    //     //     $q2->whereIn('id', $filters['categories'])))
    //     // ->when(count($filters['familys'])> 0, fn($q) =>
    //     //     $q->whereHas('category.familia', fn($q2) =>
    //     //     $q2->whereIn('id', $filters['familys'])))
    //     // ->when(count($filters['sections'])> 0, fn($q) =>
    //     //     $q->whereHas('category.familia.seccion', fn($q2) =>
    //     //     $q2->whereIn('id', $filters['sections'])))
    //     ->where('_status','!=',4)
    //     ->chunk(50, function($products) use (&$report) {
    //     foreach ($products as $product) {
    //         $report[] = $product;
    //     }
    //     });
    //     $res = [
    //         "categories"=> ProductCategoriesVA::whereNotNull('alias')->get(),
    //         "report"=>$report
    //     ];

    //     return response()->json($res);
    // }

    public function reportWarehouses(Request $request){
        $filters = $request->all();
        return response()->stream(function () use ($filters) {
            echo '['; // inicio del array JSON
            $first = true;
            ProductVA::with([
                'providers',
                'makers',
                'category.familia.seccion',
                'prices',
                'stocks' => fn($q) => $q->where('active',1),
                // 'sales' => fn($q) => $q->whereYear('created_at','>=',now()->year - 1),
                'sales' => fn($q) => $q->with([
                    'cashRegister' => fn($q2) => $q2->with([
                        'workpoint' => fn($q3) => $q3->where('active',1)
                        ])
                    ])
                ->whereYear('created_at','>=',now()->year - 1),
                'purchases'=> fn($q) => $q->whereYear('created_at',now()->year)
            ])
            ->when(count($filters['categories']) > 0, fn($q) =>
                $q->whereHas('category', fn($q2) =>
                $q2->whereIn('id', $filters['categories'])))
            ->when(count($filters['familys'])> 0, fn($q) =>
                $q->whereHas('category.familia', fn($q2) =>
                $q2->whereIn('id', $filters['familys'])))
            ->when(count($filters['sections'])> 0, fn($q) =>
                $q->whereHas('category.familia.seccion', fn($q2) =>
                $q2->whereIn('id', $filters['sections'])))
            ->where('_status','!=',4)
            ->chunk(500, function($products) use (&$first) {
                foreach ($products as $product) {
                    if (!$first) {
                        echo ','; // separador entre objetos JSON
                    }
                    echo json_encode($product);
                    $first = false;
                    // Esto envía el buffer al navegador para no acumular memoria
                    ob_flush();
                    flush();
                }
            });

            echo ']'; // cierre del array JSON
        }, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache'
        ]);
    }
}
