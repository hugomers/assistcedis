<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Barryvdh\Snappy\Facades\SnappyImage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class SalesController extends Controller
{
    public function Index(){
        $stores = Stores::whereNotIn('id',[1,2,5,6,14,15,21])->get();
        return response()->json($stores);
    }

    public function GetReportVhelp($month){
        $stores = Stores::whereNotIn('id',[1,2,5,6,14,15,21])->get();
        // return response()->json($stores);

        foreach ($stores as $store) {
            // echo($store->name);
            try {
                // $response = Http::timeout(5)->get("http://192.168.10.160:1619/storetools/public/api/reports/getSales");
                // return $response;
                $response = Http::timeout(10)->get("http://{$store->ip_address}/access/public/reports/getSalesPerMonth/{$month}");
                if ($response->ok()) {

                    $data = $response->json();
                    $store->sales = $data;
                } else {
                    $store->sales = [
                        'sucursal' => strtoupper($store->name),
                        'anterior' => [
                            "total"=>0,
                            "tickets"=>0
                        ],
                        'total' => 0,
                        'tickets' => 0,
                        'desglose' => [],
                        'status' => false
                    ];
                }
            } catch (\Exception $e) {
                $store->sales = [
                    'sucursal' => strtoupper($store->name),
                    'anterior' => [
                        "total"=>0,
                        "tickets"=>0
                    ],
                    'total' => 0,
                    'tickets' => 0,
                    'desglose' => [],
                    'status' => false
                ];
            }
        }
        return response()->json($stores,200);
    }

    public function generate(){
        $sales = [];
        // $stores = Stores::whereIn('id',[1])->get();
        $stores = Stores::whereNotIn('id',[1,2,5,6,14,15,21])->get();

        foreach ($stores as $store) {
            // echo($store->name);
            try {
                // $response = Http::timeout(5)->get("http://192.168.10.160:1619/storetools/public/api/reports/getSales");
                // return $response;
                $response = Http::timeout(5)->get("http://{$store->ip_address}/storetools/public/api/reports/getSales");
                if ($response->ok()) {

                    $data = $response->json();
                    $sales[] = [
                        'sucursal' => strtoupper($store->name),
                        'anterior' => $data['salesAnt'],
                        'total' => $data['saleshoy'] ?? 0,
                        'tickets' => $data['hoytck'] ?? 0,
                        'desglose' => $data['desglose'] ?? 0,
                        'status' => true
                    ];
                } else {
                    $sales[] = [
                        'sucursal' => strtoupper($store->name),
                        'anterior' => [
                            "total"=>0,
                            "tickets"=>0
                        ],
                        'total' => 0,
                        'tickets' => 0,
                        'desglose' => [],
                        'status' => false
                    ];
                }
            } catch (\Exception $e) {
                $sales[] = [
                    'sucursal' => strtoupper($store->name),
                    'anterior' => [
                        "total"=>0,
                        "tickets"=>0
                    ],
                    'total' => 0,
                    'tickets' => 0,
                    'desglose' => [],
                    'status' => false
                ];
            }
        }
        // return $sales;
        usort($sales, fn($a, $b) => $b['total'] <=> $a['total']);
        $html = view('sales_table', [
            'data' => $sales
        ])->render();
        $filename = 'sales_' . Str::random(8) . '.png';
        $tempPath = storage_path("app/$filename");
        SnappyImage::loadHTML($html)->save($tempPath);
        $imageData = file_get_contents($tempPath);
        $this->sendToWhatsApp($imageData);
        File::delete($tempPath);
        // return response()->json(['status' => 'Imagen enviada y eliminada']);
        return response()->json($sales);

    }

    protected function sendToWhatsApp($imageData){
        $tokem = env('WATO');
        $to = env('groupSales');
        // $to = '5573461022';
        $url = env('URLIMG');

        $payload = [
            'to' => $to,
            'caption' => 'Buenas Tardes les comparto las ventas de el dia de hoy :)',
        ];
        $payload['image'] = base64_encode($imageData);
        $response = Http::withOptions([
            'verify' => false,
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'token' => $tokem,
            'to' => $payload['to'],
            'caption' => $payload['caption'],
            'image' => $payload['image'],
        ]);
    }

    public function getSale(Request $request){
        $sales = [];
        $lasToday = Carbon::parse($request->lastDay);
        $today = Carbon::parse($request->day);;
        $data = [
        'day' => $today->format('Y-m-d'),
        'month' => $today->month,
        'year' => $today->year,
        'lastYear' => $today->year - 1,
        'lastDay' => $lasToday->format('Y-m-d'),
        ];
        // return $data;
        // $response = Http::post("http://192.168.10.160:1619/storetools/public/api/reports/getSalesPerMonth",$data);
        // return $response;
        // $response = Http::timeout(5)->post("http://{$store->ip_address}/storetools/public/api/reports/getSalesPerMonth",$data);
        $stores = Stores::whereNotIn('id',[1,2,6,5,14,15])->get();
        // $stores = Stores::whereIn('id',[3,4])->get();

        foreach($stores as $store){
            // return $store;
        try {
            $response = Http::post("http://{$store->ip_address}/storetools/public/api/reports/getSalesPerMonth",$data);
            // return $response;
            if($response->ok()){
                $sales[] = [
                    'sucursal' =>[
                        "id"=>$store->id,
                        "name" =>strtoupper($store->name),
                        "alias" => strtoupper($store->alias)
                    ] ,
                    'data'=> $response->json(),
                    'status' => true
                ];
            }else{
                $sales[] = [
                    'sucursal' =>[
                        "id"=>$store->id,
                        "name" =>strtoupper($store->name),
                        "alias" => strtoupper($store->alias)
                    ] ,
                    'data'=>[
                    "salesAct" => [
                        "desglose" => [],
                        "total" => 0,
                        "tickets" => 0,
                        "totalMonth" => 0,
                        "ticketsMonth" => 0,
                    ],
                    "salesAnt" => [
                        "desglose" => [],
                        "total" => 0,
                        "tickets" => 0,
                        "totalMonth" => 0,
                        "ticketsMonth" => 0,
                    ],],
                    'status' => false,
                    'message'=>'sinConexion'
                ];
            }
        } catch (\Exception $e) {
            $sales[] = [
                'sucursal' =>[
                    "id"=>$store->id,
                    "name" =>strtoupper($store->name),
                    "alias" => strtoupper($store->alias)
                ] ,
                'data' => [
                "salesAct" => [
                    "desglose" => [],
                    "total" => 0,
                    "tickets" => 0,
                    "totalMonth" => 0,
                    "ticketsMonth" => 0,
                ],
                "salesAnt" => [
                    "desglose" => [],
                    "total" => 0,
                    "tickets" => 0,
                    "totalMonth" => 0,
                    "ticketsMonth" => 0,
                ],
            ],
            'status'=>false,
            'message'=>$e->getMessage()
            ];
        }
        }
        return response()->json($sales,200);
    }
}
