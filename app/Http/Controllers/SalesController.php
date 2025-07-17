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

class SalesController extends Controller
{
    public function Index(){
        $stores = Stores::whereNotIn('id',[1,2,5,14,15])->get();
        return response()->json($stores);
    }

    public function generate(){
        $sales = [];
        $stores = Stores::whereNotIn('id',[1,2,5,14,15])->get();
        foreach ($stores as $store) {
            // echo($store->name);
            try {
                // $response = Http::timeout(5)->get("http://192.168.10.160:1619/storetools/public/api/reports/getSales");
                $response = Http::timeout(5)->get("http://{$store->ip_address}/storetools/public/api/reports/getSales");
                if ($response->ok()) {
                    $data = $response->json();
                    $sales[] = [
                        'sucursal' => strtoupper($store->name),
                        'total' => $data['saleshoy'] ?? 0,
                        'tickets' => $data['hoytck'] ?? 0,
                        'desglose' => $data['desglose'] ?? 0,
                        'status' => true
                    ];
                } else {
                    $sales[] = [
                        'sucursal' => strtoupper($store->name),
                        'total' => 0,
                        'tickets' => 0,
                        'desglose' => [],
                        'status' => false
                    ];
                }
            } catch (\Exception $e) {
                $sales[] = [
                    'sucursal' => strtoupper($store->name),
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
        ]);}


}
