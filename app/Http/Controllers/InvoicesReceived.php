<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Stores;
use App\Models\Invoice;
use App\Models\InvoiceBodies;
use App\Models\partitionRequisition;
use App\Models\partitionLog;
use App\Models\Transfers;
use App\Models\Warehouses;
use App\Models\WorkpointVA;
use App\Models\User;
use App\Models\ProductVA;
use App\Models\ProductCategoriesVA;
use App\Models\InvocidReceivedVA;
use App\Models\ProductReceivedVA;
use Carbon\CarbonImmutable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class InvoicesReceived extends Controller
{

    public function getInvoices(){
        $cedis = Stores::find(1);
        $anios = [
            ["anio" => 2020],
            ["anio" => 2021],
            ["anio" => 2022],
            ["anio" => 2023],
            ["anio" => 2024],
            ["anio" => 2025],

        ];
        $noEncontrados = []; // Array para productos no encontrados
        foreach($anios as $anio){
            // $response = HTTP::post($cedis->ip_address.'/storetools/public/api/invoiceReceived/getIndex', $anio);
            $response = HTTP::post('192.168.10.160:1619/storetools/public/api/invoiceReceived/getIndex', $anio);

            $facturas = $response->json();

            foreach ($facturas as $item) {
                // Validar si ya existe la factura
                $exists = InvocidReceivedVA::where('serie', $item['serie'])
                    ->where('code', $item['code'])
                    ->where('ref', $item['ref'])
                    ->where('created_at',$item['created_at'])
                    ->first();

                if ($exists) continue;
                $factura = InvocidReceivedVA::create([
                    'serie' => $item['serie'],
                    'code' => $item['code'],
                    'ref' => $item['ref'],
                    '_provider' => $item['_provider'],
                    'description' => $item['description'],
                    'total' => $item['total'],
                    'created_at' => $item['created_at'],
                ]);
                $productos = [];
                foreach ($item['products'] as $prod) {
                    $productModel = ProductVA::where('code', $prod['_product'])->first();

                    if ($productModel) {
                        $productos[$productModel->id] = [
                            'amount' => $prod['amount'],
                            'price' => $prod['price'],
                            'total' => $prod['total']
                        ];
                    } else {
                        $noEncontrados[] = [
                            'factura' => $item['code'],
                            'product_code' => $prod['_product'],
                            'anio' => $anio['anio']
                        ];
                    }
                }
                $factura->products()->sync($productos);
            }
        }
        if (!empty($noEncontrados)) {
            Log::channel('daily')->warning('Productos no encontrados en sincronización de facturas:', $noEncontrados);
        }
        return response()->json([
            'status' => 'ok',
            'no_encontrados' => $noEncontrados
        ]);
    }



    public function replyInvoices(){
        $cedis = Stores::find(1);
        $invoice = InvocidReceivedVA::max('created_at');
        $data =  [
            'anio' => Carbon::parse($invoice)->format('Y'),
            'date' => Carbon::parse($invoice)->format('Y/m/d'),
        ];
        // return $data;
        $noEncontrados = [];
        $response = HTTP::post($cedis->ip_address.'/storetools/public/api/invoiceReceived/replyInvoices', $data);
        $facturas = $response->json();
        foreach ($facturas as $item) {
            $exists = InvocidReceivedVA::where('serie', $item['serie'])
                ->where('code', $item['code'])
                ->where('ref', $item['ref'])
                ->where('created_at',$item['created_at'])
                ->first();

            if ($exists) continue;
            $factura = InvocidReceivedVA::create([
                'serie' => $item['serie'],
                'code' => $item['code'],
                'ref' => $item['ref'],
                '_provider' => $item['_provider'],
                'description' => $item['description'],
                'total' => $item['total'],
                'created_at' => $item['created_at'],
            ]);
            $productos = [];
            foreach ($item['products'] as $prod) {
                $productModel = ProductVA::where('code', $prod['_product'])->first();

                if ($productModel) {
                    $productos[$productModel->id] = [
                        'amount' => $prod['amount'],
                        'price' => $prod['price'],
                        'total' => $prod['total']
                    ];
                } else {
                    $noEncontrados[] = [
                        'factura' => $item['code'],
                        'product_code' => $prod['_product'],
                        'anio' => $anio['anio']
                    ];
                }
            }
            $factura->products()->sync($productos);
        }

        if (!empty($noEncontrados)) {
            Log::channel('daily')->warning('Productos no encontrados en sincronización de facturas:', $noEncontrados);
        }
        return response()->json([
            'status' => 'ok',
            'no_encontrados' => $noEncontrados
        ]);
    }

    public function updateInvoices(){

    }
}
