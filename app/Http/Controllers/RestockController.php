<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\partitionRequisition;
use App\Models\partitionLog;
use App\Models\InvoiceBodies;
use App\Models\InvoiceStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\Transfers;
use App\Models\AccountVA;
use App\Models\Warehouses;
use App\Models\WorkpointVA;
use App\Models\ProductStockVA;
use App\Models\CellerSectionVA;
use App\Models\User;
use App\Models\CellerVA;
use App\Models\ProductVA;
use App\Models\ProductCategoriesVA;
use Carbon\CarbonImmutable;
use Carbon\Carbon;
class RestockController extends Controller
{
    public function getSupply($sid){
        $id = $sid == 24 ? 12 : $sid;
        $staff = Staff::whereIn('_store',[$id])->whereIn('_position',[6,3,2,46])->where('acitve',1)->get();
        return $staff;
    }

    // public function saveSupply(Request $request){
    //     $status = $request->status;
    //     $pedido = $request->pedido;
    //     $ubicaciones = $request->ubicaciones;
    //     $to = $request->_workpoint_to;
    //     $from = $request->_workpoint_from;
    //     $partitions = [];

    //     $locationsTo = '(SELECT CS.path FROM product_location AS PL
    //     JOIN celler_section AS CS ON CS.id = PL._location
    //     JOIN celler AS C ON C.id = CS._celler
    //     WHERE PL._product = PR._product
    //     AND CS.deleted_at IS NULL
    //     AND C._workpoint = '.$to.'
    //     ORDER BY CS.path ASC
    //     LIMIT 1) AS locationsTo';

    //     $locationsFrom = '(SELECT CS.path FROM product_location AS PL
    //     JOIN celler_section AS CS ON CS.id = PL._location
    //     JOIN celler AS C ON C.id = CS._celler
    //     WHERE PL._product = PR._product
    //     AND CS.deleted_at IS NULL
    //     AND C._workpoint = '.$from.'
    //     ORDER BY CS.path ASC
    //     LIMIT 1) AS locationsFrom';

    //     $prod = DB::connection('vizapi')
    //     ->table('product_required AS PR')
    //     ->join('products AS P','P.id','PR._product')
    //     ->select('P.code','PR._product', 'PR._requisition', DB::raw($locationsTo),DB::raw($locationsFrom))
    //     ->where('PR._requisition', $pedido)
    //     ->orderBy("locationsTo",'asc')
    //     ->orderBy("locationsFrom",'asc')
    //     ->get();

    //     $vcollect = collect($prod);
    //     $groupby = $vcollect->groupBy(function($val) {
    //         if(isset($val->locationsTo)){
    //             return explode('-',$val->locationsTo)[0];
    //         }else{ return '';}
    //     })->sortKeys();
    //     foreach($groupby as $piso){
    //         $products = $piso->sortBy(function($val){
    //             if($val){
    //                 $location = $val->locationsTo;
    //                 $res ='';
    //                 $parts = explode('-',$location);
    //                 foreach($parts as $part){
    //                     $numbers = preg_replace('/[^0-9]/', '', $part);
    //                     $letters = preg_replace('/[^a-zA-Z]/', '', $part);
    //                     if(strlen($numbers)==1){
    //                         $numbers = '0'.$numbers;
    //                     }
    //                     $res = $res.$letters.$numbers.'-';
    //                 }
    //                 return $res = $res.$letters.$numbers.'-';

    //             }
    //             return '';
    //         });
    //         foreach($products as $product){
    //             $uns []= $locations = $product;
    //          }
    //     }


    //     $asig = [];
    //     $num_productos = count($uns);
    //     $num_surtidores = count($surtidores);
    //     $supplyper = floor($num_productos / $num_surtidores);

    //     $remainder = $num_productos % $num_surtidores;
    //     $counter = 0;
    //     for ($i = 0; $i < $num_surtidores; $i ++){
    //         if($remainder > 0){
    //             $asig[] = $supplyper + 1;
    //             $remainder--;
    //         }else{
    //             $asig[]= $supplyper;
    //         }
    //     }
    //     foreach($asig as $key => $val){
    //         $surtidores[$key]['products'] = array_splice($uns,0,$val);
    //     }


    //     foreach ($surtidores as $surtidor) {
    //         $asigpro = $surtidor['products'];
    //         foreach($asigpro as $product){
    //             $upd = [
    //                 "_suplier"=>$surtidor['staff']['complete_name'],
    //                 "_suplier_id"=>$surtidor['staff']['id']
    //             ];
    //             $dbproduct = InvoiceBodies::where([['_requisition',$product->_requisition],['_product',$product->_product]])
    //             ->update($upd);
    //         }
    //     }

    //     foreach($surtidores as $surtidor){
    //         $newres = new Restock;
    //         $supply = $surtidor['staff']['id'];
    //         $newres->_staff = $supply;
    //         $newres->_requisition = $pedido;
    //         $newres->_status = $status;
    //         $newres->save();
    //         $newres->fresh()->toArray();
    //         $ins = [
    //             "_requisition"=>$pedido,
    //             "_suplier_id"=>$supply,
    //             "_suplier"=>$surtidor['staff']['complete_name'],
    //             "_status"=>$status
    //         ];
    //         $inspart = new partitionRequisition($ins);
    //         $inspart->save();
    //         $res = $inspart->load( ['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
    //         $partitions[] = $res;
    //         $setted = InvoiceBodies::where([['_requisition',$pedido],['_suplier_id',$supply]])->update(['_partition'=>$res->id]);
    //     }
    //     return response()->json($partitions,200);
    // }

    public function saveSupply(Request $request){
        $partition = $request->partition;
        $supply = $request->surtidor;
        $warehouse = $request->warehouse;
        $status = $request->state;

        $change = partitionRequisition::find($partition);
        if($change->_status == 3){
            $change->_suplier =$supply['complete_name'] ;
            $change->_suplier_id = $supply['id'];
            $change->_status = $status;
            $change->save();
            $freshPartition = $change->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
            $idlog = partitionLog::max('id') + 1;

            $inslo = [
                'id'=>$idlog,
                '_requisition'=>$freshPartition->_requisition,
                '_partition'=>$freshPartition->id,
                '_status'=>$status,
                'details'=>json_encode(['responsable'=>$freshPartition->getSupplyStaff()->complete_name]),
            ];

            $logs = partitionLog::insert($inslo);
            $endpart = $this->verifyPartition($freshPartition->_requisition);
            $res = [
                "partition"=>$freshPartition,
                "partitionsEnd"=>$endpart
            ];
            return response()->json($res,200);
        }else{
            return response()->json(['message'=>'El status ya esta surtido'],401);
        }

    }

    public function createParitions(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $ubicaciones = $request->ubicaciones;
        $to = $request->_workpoint_to;
        $from = $request->_workpoint_from;

        // $products = [];
        $order = Invoice::with([
            'type',
            'status',
            'log',
            'from',
            'to',
            'partition.status',
            'partition.log',
            'partition.products',
        ])->findOrFail($pedido);
        $toWorkpointId = $order->to->id;
        $order->load([
            'products' => fn($q) => $q->whereHas('stocks', fn($s) =>
                $s->where('_workpoint', $toWorkpointId)->where('stock', '>', 0)
            ),
            'products.category.familia.seccion',
            'products.units',
            'products.variants',
            'products.stocks' => fn($q) => $q->whereIn('_workpoint', [1,2]),
            'products.locations' => fn($q) =>
                $q->whereHas('celler', fn($l) => $l->where('_workpoint', $toWorkpointId))
                ->whereNull('deleted_at'),
        ]);
        $productosAsignados = collect(); // Control de productos ya asignados
        $partitions = [];

        foreach ($ubicaciones as $ubicacion) {
            $rootId = $ubicacion['id'] ?? null;
            $partitionCount = (int) $ubicacion['partition'];


            $productosFiltrados = $order->products->filter(function ($producto) use ($rootId, $productosAsignados) {
                if ($productosAsignados->contains($producto->id)) {
                    return false;
                }

                if (is_null($rootId)) {
                    return true;
                }

                foreach ($producto->locations as $loc) {
                    $rootNode = $loc->getRootNode();
                    if ($rootNode && $rootNode->id == $rootId) {
                        return true;
                    }
                }

                return false;
            });

            $productosAsignados = $productosAsignados->merge($productosFiltrados->pluck('id'));
            $productosArray = $productosFiltrados->values();
            $totalProductos = $productosArray->count();
            $chunkSize = $partitionCount > 0 ? (int) ceil($totalProductos / $partitionCount) : $totalProductos;

            for ($i = 1; $i <= $partitionCount; $i++) {
                $npartition = new partitionRequisition([
                    '_requisition' => $order->id,
                    '_status' => $status,
                    '_warehouse' => $order->_warehouse
                ]);
                $npartition->save();

                if ($totalProductos > 0) {
                    $productosParaEstaPartition = $productosArray->slice(($i - 1) * $chunkSize, $chunkSize);
                    foreach ($productosParaEstaPartition as $producto) {
                        $order->products()->updateExistingPivot($producto->id, [
                            '_partition' => $npartition->id,
                        ]);
                    }
                }

                $reqio = $npartition->load([
                    'status',
                    'log',
                    'products.locations' => fn($q) => $q->whereHas('celler', fn($l) => $l->where('_workpoint', $toWorkpointId))->whereNull('deleted_at'),
                    'requisition.type',
                    'requisition.status',
                    'requisition.to',
                    'requisition.from',
                    'requisition.created_by',
                    'requisition.log'
                ]);

                $ip = null;
                switch ($toWorkpointId) {
                    case 1:
                        $ip = env('PRINTER_P3');
                        break;
                    case 2:
                        $ip = env('PRINTERTEX');
                        break;
                    case 16:
                        $ip = env('PRINTERBRASIL');
                        break;

                    default:
                        $ip = env('PRINTER_P3');
                        break;
                }

                $ip = $toWorkpointId == 2 ? env('PRINTERTEX') : env('PRINTER_P3');

                $cellerPrinter = new PrinterController();
                $cellerPrinter->PartitionTicket($ip, $reqio);
                $partitions[] = $reqio;
            }
        }

        return response()->json($partitions,200);
    }

    public function pritnforPartition(Request $request){
        $ip = $request->ip;
        $port = $request->port;
        $workpoint_to = $request->_workpoint_to;
        $requisition = partitionRequisition::find($request->_partition);
        // $workpoint_to_print = Workpoint::find(1);
        $requisition->load(['requisition.from','requisition.created_by','requisition.to', 'log', 'products' => function($query) use($workpoint_to)  {
            $query->with(['locations' => function($query) use($workpoint_to){
                $query->whereHas('celler', function($query) use($workpoint_to){
                    $query->where('_workpoint', $workpoint_to);
                })->whereNull('deleted_at');
            }]);
        }]);
        // return $requisition;
        $cellerPrinter = new PrinterController();
        $cellerPrinter;
        $res = $cellerPrinter->PartitionTicket($ip, $requisition);
        return response()->json(["success" => $res, "printer" => $ip]);
    }

    public function saveVerified(Request $request){
        $partition = $request->partition;
        $verificador = $request->verified;
        $supply = $request->surtidor;
        $warehouse = $request->warehouse;
        $status = $request->state;

        $change = partitionRequisition::find($partition);
        $change->_out_verified = $verificador;
        $change->entry_key = md5($partition);
        // $change->_warehouse = $warehouse;
        $change->_status = $status;
        $change->save();
        $freshPartition = $change->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
        $products = $freshPartition->products;
        $from = $freshPartition->requisition['from']['id'];
        if($warehouse == 'GEN'){
        $transit  = $this->modifyTransit($products,$from);
        }
        $idlog = partitionLog::max('id') + 1;

        $inslo = [
            'id'=>$idlog,
            '_requisition'=>$freshPartition->_requisition,
            '_partition'=>$freshPartition->id,
            '_status'=>$status,
            'details'=>json_encode(['responsable'=>$freshPartition->getOutVerifiedStaff()->complete_name]),
        ];

        $logs = partitionLog::insert($inslo);
        $endpart = $this->verifyPartition($freshPartition->_requisition);
        $res = [
            "partition"=>$freshPartition,
            "partitionsEnd"=>$endpart
        ];

        return response()->json($res,200);
    }

    public function modifyTransit($products, $workpoint){
        $counts = $products->filter(function ($val) {
            return !empty($val['pivot']['checkout']);
        });
        foreach ($counts as $product) {
            $mul = match ($product['pivot']['_supply_by']) {
                2 => 12,
                3 => $product['pivot']['ipack'] ?? 1,
                default => 1,
            };
            $canti = $product['pivot']['toDelivered'] * $mul;
            ProductStockVA::where([
                ['_product', $product['id']],
                ['_workpoint', $workpoint]
            ])->increment('in_transit', $canti);
        }
    }

    public function modifyTransitReceived($products,$workpoint){
        $counts = $products->filter(function ($val) {
            return !empty($val['pivot']['checkout']);
        });
        foreach ($counts as $product) {
            $mul = match ($product['pivot']['_supply_by']) {
                2 => 12,
                3 => $product['pivot']['ipack'] ?? 1,
                default => 1,
            };
            $canti = $product['pivot']['toDelivered'] * $mul;
            ProductStockVA::where([
                ['_product', $product['id']],
                ['_workpoint', $workpoint]
            ])->decrement('in_transit', $canti);
            // ProductStockVA::where([
            //     ['_product', $product['id']],
            //     ['_workpoint', $workpoint]
            // ])->increment('gen', $canti);
            ProductStockVA::where([
                ['_product', $product['id']],
                ['_workpoint', $workpoint]
            ])->increment('stock', $canti);
        }
    }

    public function getVerified($sid){
        $id = $sid == 24 ? 12 : $sid;
        $staff = Staff::whereIn('_store',[$id])->whereIn('_position',[6,10,1])->where('acitve',1)->get();
        return $staff;
    }

    public function saveChofi(Request $request){
        $partition = $request->partition;
        $chofer = $request->chofi;
        $status = $request->state;

        $change = partitionRequisition::find($partition);
        $change->_driver = $chofer;
        $change->_status = $status;
        $change->save();
        $partition = $change->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
        $idlog = partitionLog::max('id') + 1;

        $inslo = [
            'id'=>$idlog,
            '_requisition'=>$partition->_requisition,
            '_partition'=>$partition->id,
            '_status'=>$status,
            'details'=>json_encode(['responsable'=>$partition->getOutDrivingStaff()->complete_name]),
        ];
        $logs = partitionLog::insert($inslo);
        $endpart = $this->verifyPartition($partition->_requisition);

        if($change){
            $message = 'El colaborador '.$partition->getOutDrivingStaff()->complete_name.' transporta el pedido '.$partition->id.' de la sucursal '.$partition->requisition['from']['name'];
            $to = '120363194490127898@g.us';
            // $to = '5573461022';
            $sendMessage = $this->envMssg($message,$to);
        }
        $res = [
            "partition"=>$partition,
            "partitionsEnd"=>$endpart
        ];
        return response()->json($res,200);
    }

    public function saveReceipt(Request $request){
        $partition = $request->partition;
        $chofer = $request->chofi;
        $status = $request->state;
        $change = partitionRequisition::find($partition);
        // $change->_driver = $chofer;
        $change->_status = $status;
        $change->save();
        $partition = $change->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
        $idlog = partitionLog::max('id') + 1;

        $inslo = [
            'id'=>$idlog,
            '_requisition'=>$partition->_requisition,
            '_partition'=>$partition->id,
            '_status'=>$status,
            'details'=>json_encode(['responsable'=>$partition->getOutDrivingStaff()->complete_name]),
        ];
        $logs = partitionLog::insert($inslo);
        $endpart = $this->verifyPartition($partition->_requisition);
        if($change){
            $message = 'El colaborador '.$partition->getOutDrivingStaff()->complete_name.' entrego el pedido '.$partition->id.' de la sucursal '.$partition->requisition['from']['name'];
            $to = '120363194490127898@g.us';
            // $to = '5573461022';
            $sendMessage = $this->envMssg($message,$to);
        }
        $res = [
            "partition"=>$partition,
            "partitionsEnd"=>$endpart
        ];
        return response()->json($res,200);
    }

    public function getChof($sid){
        $id = $sid == 24 ? 12 : $sid;
        $staff = Staff::whereIn('_store',[$id])->whereIn('_position',[3])->where('acitve',1)->get();

        return $staff;
    }

    public function getCheck($cli){
        $store = Stores::with('Staff')->where('_client',$cli)->value('id');
        $staff = Staff::where('_store',$store,)->whereIn('_position',[7,8,16,17,23])->get();

        return $staff;
    }

    public function saveCheck(Request $request){
        $partition = $request->partition;
        $check = $request->verified;
        $status = $request->state;
        $entry_key = $request->key;
        $change = partitionRequisition::find($partition);
        $bfore = $change->_status;

        if($change->entry_key == $entry_key){
        // $change = partitionRequisition::find($partition);
            $change->_in_verified = $check;
            $change->_status = $status;
            $change->save();
            $partition = $change->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
            if($bfore == 7){
                $message = 'El colaborador '.$partition->getOutDrivingStaff()->complete_name.' entrego el pedido '.$partition->id.' de la sucursal '.$partition->requisition['from']['name'];
                $to = '120363194490127898@g.us';
                // $to = '5573461022';
                $sendMessage = $this->envMssg($message,$to);
            }
            $idlog = partitionLog::max('id') + 1;

            $inslo = [
                'id'=>$idlog,
                '_requisition'=>$partition->_requisition,
                '_partition'=>$partition->id,
                '_status'=>$status,
                'details'=>json_encode(['responsable'=>$partition->getCheckStaff()->complete_name]),
            ];
            $logs = partitionLog::insert($inslo);
            $endpart = $this->verifyPartition($partition->_requisition);

            $res = [
                "partition"=>$partition,
                "partitionsEnd"=>$endpart
            ];
            return response()->json($res,200);

        }else{
            return response()->json(["message"=>"La llave no coincide"],401);
        }


    }

    public function refresTransit(Request $request){
        $freshPartition = partitionRequisition::with(['products','requisition.from'])->find($request->id);
        $products = $freshPartition->products;
        $from = $freshPartition->requisition['from']['id'];
        $transit  = $this->modifyTransitReceived($products,$from);
    }

    public function getSalida(Request $request){
        $salida = $request->all();
        $stores = Stores::find(1);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.112:1619';
        $getdev = Http::post($ip.'/storetools/public/api/Resources/returnFac',$salida);
        if($getdev->status() != 200){
            return false;
        }else{
            return $getdev;
        }

    }

    public function changeStatus(Request $request){
        $pedido = $request->id;
        $status = $request->state;

        $responsable = null;
        $partitions = partitionRequisition::find($pedido);
        if($partitions->_status > $status){
            return response()->json(['message'=>'No puedes cambiar de el status '.$partitions->_status.' al '.$status ],500);
        }
        $partitions->_status = $status;
        $partitions->save();
        // 'type', 'status', 'to', 'from', 'created_by', 'log', 'partition.status', 'partition.log'
        $partition = $partitions->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
        switch ($status) {
            // case 6:
            //  $responsable = $partition->getOutVerifiedStaff()->complete_name;
            //     break;
            case 10:
            $responsable =  $partition->getCheckStaff()->complete_name;
                break;
            default:
            $responsable =  'Vizapp';
                break;
        }
        $idlog = partitionLog::max('id') + 1;

        $inslo = [
            'id'=>$idlog,
            '_requisition'=>$partition->_requisition,
            '_partition'=>$partition->id,
            '_status'=>$status,
            'details'=>json_encode(['responsable'=>$responsable]),
        ];

        $logs = partitionLog::insert($inslo);
        $endpart = $this->verifyPartition($partition->_requisition);
        $res = [
            "partition"=>$partition,
            "partitionsEnd"=>$endpart
        ];
        return response()->json($res,200);
    }

    public function sendMessages(Request $request){
        $url = env('URLWHA');
        $token = env('WATO');
        $pedido = $request->id;
        $suply = $request->suply;
        $chofer = Staff::where('id',$suply)->first();
        $sucursal = $request->store;

        $response = Http::withOptions([
            'verify' => false, // Esto deshabilita la verificación SSL, similar a CURLOPT_SSL_VERIFYHOST y CURLOPT_SSL_VERIFYPEER en cURL
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'token' => $token,
            'to' => '120363194490127898@g.us',
            'body' => 'El colaborador '.$chofer->complete_name.' entrego la salida  '.$pedido.' a la sucursal '.$sucursal,
        ]);
    }

    public function verifyPartition($pedido){
        $partition = partitionRequisition::where([['_requisition',$pedido]])->min('_status');
        return $partition;
    }

    public function getInvoices(){
        $work = [];

        try {
            // Buscar el cedis
            $cedis = Stores::find(1);
            if (!$cedis) {
                return response()->json(['message' => 'Cedis no encontrado'], 404);
            }

            // Obtener facturas
            $invoicesResponse = Http::get($cedis->ip_address.'/storetools/public/api/Resources/Invoices');
            if ($invoicesResponse->failed()) {
                return response()->json(['message' => 'Hubo un problema con el servidor al obtener las facturas'], 401);
            }
            $invoices = $invoicesResponse->json();

            // Agrupar facturas por cliente y factura
            $groupedInvoices = [];
            foreach ($invoices as $invoice) {
                $client = $invoice['CLIFAC'];
                $factura = 'FAC '.$invoice['FACTURA'];
                if (!isset($groupedInvoices[$client])) {
                    $groupedInvoices[$client] = [];
                }
                if (!isset($groupedInvoices[$client][$factura])) {
                    $groupedInvoices[$client][$factura] = [];
                }
                $groupedInvoices[$client][$factura][] = $invoice;
            }

            // Obtener tiendas, excluyendo los IDs 1 y 2
            $stores = Stores::whereNotIn('id', [1, 2,5,14,15,])->get();

            foreach ($stores as $store) {
                // Obtener entradas para cada tienda
                $entriesResponse = Http::get($store->ip_address.'/storetools/public/api/Resources/Entries');
                if ($entriesResponse->successful()) {
                    $entries = $entriesResponse->json();

                    // Agrupar entradas por cliente y factura
                    $groupedEntries = [];
                    foreach ($entries as $entry) {
                        $client = $store->_client;
                        $factura = $entry['FACFRE'];
                        if (!isset($groupedEntries[$client])) {
                            $groupedEntries[$client] = [];
                        }
                        if (!isset($groupedEntries[$client][$factura])) {
                            $groupedEntries[$client][$factura] = [];
                        }
                        $groupedEntries[$client][$factura][] = $entry;
                    }

                    foreach ($groupedInvoices as $client => $facturas) {
                        foreach ($facturas as $factura => $invoicesList) {
                            // Verificar si el cliente pertenece al store_id
                            $storeForClient = Stores::where('_client', $client)->first();
                            if ($storeForClient && $storeForClient->id == $store->id) {
                                // Verificar si hay entradas correspondientes
                                if (isset($groupedEntries[$client][$factura])) {
                                    // Si hay entradas, agregar la factura con las entradas
                                    $entryList = $groupedEntries[$client][$factura];
                                    $work[] = [
                                        'store_id' => $store->id,
                                        'store_name'=>$store->name,
                                        'client' => $client,
                                        'factura' => $factura,
                                        'entries' => $entryList,
                                        'invoices' => $invoicesList
                                    ];
                                } else {
                                    // Si no hay entradas, agregar solo la factura
                                    $work[] = [
                                        'store_id' => $store->id,
                                        'store_name'=>$store->name,
                                        'client' => $client,
                                        'factura' => $factura,
                                        'entries' => [],
                                        'invoices' => $invoicesList
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            return response()->json($work, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error: ' . $e->getMessage()], 500);
        }
    }

    public function getTransfer(Request $request){
        $traspaso = $request->all();
        $stores = Stores::find(1);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.160:1619';
        $getdev = Http::post($ip.'/storetools/public/api/Resources/returnTra',$traspaso);
        if($getdev->status() != 200){
            return false;
        }else{
            return $getdev;
        }
    }

    public function getStores(){
        $stores = Stores::whereNotIn('id', [2,5,14,15,])->get();
        return response()->json($stores,200);
    }

    // public function getData(Request $request){
    //     $cedis = $request->cedis;
    //     $sucursal= $request->sucursal;
    //     $fechas = $request->fechas;
    //     $clente = $sucursal['_client'];
    //     // $invoicesResponse = Http::timeout(200)->post('192.168.10.160:1619'.'/storetools/public/api/Resources/getInvoices', ['_client'=> $clente, 'fechas'=>$fechas] );
    //     $invoicesResponse = Http::timeout(500)->post($cedis['ip_address'].'/storetools/public/api/Resources/getInvoices', ['_client'=> $clente, 'fechas'=>$fechas] );

    //     if($invoicesResponse->status() == 200){
    //         $unicos = array_values(array_unique(array_map(function($val){return "'".'P-'.$val['PARTICION']."'";},$invoicesResponse->json())));
    //         $salidas =  implode(',',$unicos);
    //         // return $salidas;
    //         // $entriesResponse = Http::timeout(200)->post('192.168.10.160:1619'.'/storetools/public/api/Resources/getEntries',["invoices"=>$salidas]);
    //         $entriesResponse = Http::timeout(500)->post($sucursal['ip_address'].'/storetools/public/api/Resources/getEntries',["invoices"=>$salidas]);
    //         $res = [
    //             "unicos"=>$salidas,
    //             "salidas"=>json_decode($invoicesResponse),
    //             "entradas"=>json_decode($entriesResponse),
    //         ];
    //         return $res;
    //     }
    // }

    public function getData(Request $request){
        $cedis = $request->cedis;
        $sucursal = $request->sucursal;
        $fechas = $request->fechas;
        $salidas = [];
        $entradas = [];
        $cliente = $sucursal['_client'] ?? null; // Por si acaso no viene definido
        if (isset($fechas['from']) && isset($fechas['to'])) {
            $desde = $fechas['from'];
            $hasta = $fechas['to'];
        } else {
            $desde = $fechas;
            $hasta = $fechas;
        }
        $partitions = partitionRequisition::with(['status'])->whereHas('requisition', function ($q) use ($sucursal, $desde, $hasta) {
            $q->where('_workpoint_from', $sucursal['id_viz'])
                ->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta]);
        })
        ->where('_status','>=',6)
        ->get();
        $invoice =   $partitions->pluck('invoice')->filter()->values()->toArray();
        $invoiceReceived = $partitions->pluck('invoice_received')->filter()->values()->toArray();
        // $invoicesResponse = Http::timeout(100000)->post('192.168.10.160:1619'.'/storetools/public/api/Resources/getInvoices',  ['invoices'=> $invoice] );
        $invoicesResponse = Http::timeout(100000)->post($cedis['ip_address'].'/storetools/public/api/Resources/getInvoices', ['invoices'=> $invoice] );
        if($invoicesResponse->status() == 200){
            $salidas = collect(json_decode($invoicesResponse->body(), true));
        }


        // $entriesResponse = Http::timeout(100000)->post('192.168.10.160:1619'.'/storetools/public/api/Resources/getEntries',["invoices"=>$invoiceReceived]);
        $entriesResponse = Http::timeout(100000)->post($sucursal['ip_address'].'/storetools/public/api/Resources/getEntries',["invoices"=>$invoiceReceived]);
        if($entriesResponse->status() == 200){
             $entradas = collect(json_decode($entriesResponse->body(), true));
        }
        $merged = $partitions->map(function ($part) use ($salidas, $entradas) {
            $salida = collect($salidas)->firstWhere('FACTURA', $part->invoice);
            $entrada = collect($entradas)->firstWhere('FACTURA', $part->invoice_received);
            $data = $part->toArray();
            $data['salida'] = $salida;
            $data['entrada'] = $entrada;

            $comparaciones = [];

            $salidaProducts = collect($salida['products'] ?? []);
            $entradaProducts = collect($entrada['products'] ?? []);
            foreach ($salidaProducts as $prodSalida) {
                $prodEntrada = $entradaProducts->firstWhere('ARTICULOS', $prodSalida['ARTICULOS']);

                $comparaciones[] = [
                    'SALIDA_FACTURA' => $salida['FACTURA'] ?? null,
                    'SALIDA_FECHA' => $salida['FECHAYHORA'] ?? null,
                    'REFERENCIA' => $salida['REFERENCIA'] ?? null,
                    'CLIENTE' => $salida['NOMBRECLIENTE'] ?? null,
                    'CODIGO' => $prodSalida['ARTICULOS'],
                    'DESCRIPCION' => $prodSalida['DESCRIPCION'],
                    'CANTIDAD' => $prodSalida['CANTIDAD'],
                    // 'PRECIO' => $prodSalida['PRECIO'],
                    // 'TOTAL' => $prodSalida['TOTAL'],

                    // Comparaciones
                    'codigoIGUAL' => $prodEntrada ? $prodEntrada['ARTICULOS'] === $prodSalida['ARTICULOS'] : false,
                    'CANTIDADIGUAL' => $prodEntrada ? $prodEntrada['CANTIDAD'] === $prodSalida['CANTIDAD'] : false,
                    'PRECIOIGUAL' => $prodEntrada ? $prodEntrada['PRECIO'] === $prodSalida['PRECIO'] : false,
                    'TOTALIGUAL' => $prodEntrada ? $prodEntrada['TOTAL'] === $prodSalida['TOTAL'] : false,

                    // Datos de entrada (si existen)
                    'ENTRADA_FACTURA' => $entrada['FACTURA'] ?? null,
                    'ENTRADA_SALIDA' => $entrada['SALIDA'] ?? null,
                    'ENTRADA_FECHA' => $entrada['FECHA'] ?? null,
                    'ENTRADA_CODIGO' => $prodEntrada['ARTICULOS'] ?? null,
                    'ENTRADA_DESCRIPCION' => $prodEntrada['DESCRIPCION'] ?? null,
                    'ENTRADA_CANTIDAD' => $prodEntrada['CANTIDAD'] ?? null,
                    // 'ENTRADA_PRECIO' => $prodEntrada['PRECIO'] ?? null,
                    // 'ENTRADA_TOTAL' => $prodEntrada['TOTAL'] ?? null,
                ];
            }

            foreach ($entradaProducts as $prodEntrada) {
                $prodSalida = $salidaProducts->firstWhere('ARTICULOS', $prodEntrada['ARTICULOS']);
                if (!$prodSalida) {
                    $comparaciones[] = [
                        'SALIDA_FACTURA' => $salida['FACTURA'] ?? null,
                        'SALIDA_FECHA' => $salida['FECHAYHORA'] ?? null,
                        'REFERENCIA' => $salida['REFERENCIA'] ?? null,
                        'CLIENTE' => $salida['NOMBRECLIENTE'] ?? null,
                        'CODIGO' => null,
                        'DESCRIPCION' => null,
                        'CANTIDAD' => null,
                        // 'PRECIO' => null,
                        // 'TOTAL' => null,

                        // Todas las comparaciones en falso
                        'codigoIGUAL' => false,
                        'CANTIDADIGUAL' => false,
                        'PRECIOIGUAL' => false,
                        'TOTALIGUAL' => false,

                        // Datos de entrada
                        'ENTRADA_FACTURA' => $entrada['FACTURA'] ?? null,
                        'ENTRADA_SALIDA' => $entrada['SALIDA'] ?? null,
                        'ENTRADA_FECHA' => $entrada['FECHA'] ?? null,
                        'ENTRADA_CODIGO' => $prodEntrada['ARTICULOS'],
                        'ENTRADA_DESCRIPCION' => $prodEntrada['DESCRIPCION'],
                        'ENTRADA_CANTIDAD' => $prodEntrada['CANTIDAD'],
                        // 'ENTRADA_PRECIO' => $prodEntrada['PRECIO'],
                        // 'ENTRADA_TOTAL' => $prodEntrada['TOTAL'],
                    ];
                }
            }
            $data['comparaciones'] = $comparaciones;
            return $data;
        });
        return $merged;
    }

    public function envMssg($message,$to){
        $url = env('URLWHA');
        $token = env('WATO');

        $response = Http::withOptions([
            'verify' => false, // Esto deshabilita la verificación SSL, similar a CURLOPT_SSL_VERIFYHOST y CURLOPT_SSL_VERIFYPEER en cURL
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'token' => $token,
            'to' => $to,
            'body' => $message,
        ]);
    }

    public function changeRaiz(){
        // return 'holi';
        $agrupaciones = [
            [
                'nuevo_path' => 'BR',
                'nuevo_nombre' => 'CDSBRASIL',
                'ids' => [2500472,2500473,2500474,2500475,2500476,2500477,2500478,2500479,2500480,2500481,2500482,2500483,2500484,2500485,2500486], // IDs de CUARTO, ESCALERAS, MONTACARGAS que deben quedar debajo de PISO 1
            ],
            // [
            //     'nuevo_path' => 'P2',
            //     'nuevo_nombre' => 'PISO 2',
            //     'ids' => [339, 457, 538, 573, 913, 10878, 15588, 2478723,246],
            // ],
            // [
            //     'nuevo_path' => 'P3',
            //     'nuevo_nombre' => 'PISO 3',
            //     'ids' => [588, 666, 792, 926, 958, 10689, 15608],
            // ],
            // [
            //     'nuevo_path' => 'P4',
            //     'nuevo_nombre' => 'PISO 4',
            //     'ids' => [990, 1046, 1069, 10952, 13813, 19321, 21800, 21801,1092],
            // ],
            // [
            //     'nuevo_path' => 'PB',
            //     'nuevo_nombre' => 'PLANTA BAJA',
            //     'ids' => [1, 224], // Aquí pones los IDs reales
            // ],
        ];

        DB::transaction(function () use ($agrupaciones) {
            foreach ($agrupaciones as $grupo) {
                // Crear nuevo nodo raíz
                $nuevo = CellerSectionVA::create([
                    'name' => $grupo['nuevo_nombre'],
                    'alias' => $grupo['nuevo_path'],
                    'path'  => $grupo['nuevo_path'],
                    'root'  => 0,
                    'deep'  => 0,
                    '_celler' => 1
                ]);

                // Buscar nodos actuales (deep = 0) por ID
                $nodos = CellerSectionVA::whereIn('id', $grupo['ids'])->get();

                foreach ($nodos as $nodo) {
                    $oldPath = $nodo->path;

                    // Actualizar nodo actual: ahora es hijo del nuevo
                    $nodo->update([
                        'root' => $nuevo->id,
                        'deep' => 1,
                        'path' => $grupo['nuevo_path'] . '-' . $nodo->alias,
                    ]);

                    // Buscar descendientes y actualizarlos
                    $descendientes = CellerSectionVA::where('path', 'like', $oldPath . '-%')->get();

                    foreach ($descendientes as $desc) {
                        $desc->update([
                            'deep' => $desc->deep + 1,
                            'path' => $grupo['nuevo_path'] . '-' . $desc->path,
                        ]);
                    }
                }
            }
        });
    }

    public function lockPartition($id) {
        $partition = partitionRequisition::where('id',$id)->update(['_blocked' => 1]);
        return response()->json(['success' => true]);
    }

    public function unlockPartition($id) {
        $partition = partitionRequisition::where('id',$id)->update(['_blocked' => 0]);
        return response()->json(['success' => true]);
    }


    public function create(Request $request){
        $_workpoint_from = $request->_workpoint_from;
        $_workpoint_to = $request->_workpoint_to;
        $request->_type;
        if( isset($request->cats)){
            $data = $this->getToSupplyFromStore($_workpoint_from, $_workpoint_to,$request->cats );
        }else{
            $data = $this->getToSupplyFromStore($_workpoint_from, $_workpoint_to);
        }
        if(isset($data['msg'])){
            return response()->json([
                "success" => false,
                "msg" => $data['msg']
            ]);
        }
        $now = new \DateTime();
        $num_ticket = Invoice::where('_workpoint_to', $_workpoint_to)
                                    ->whereDate('created_at', $now)
                                    ->count()+1;
        $num_ticket_store = Invoice::where('_workpoint_from', $_workpoint_from)
                                        ->whereDate('created_at', $now)
                                        ->count()+1;

        $requisition =  Invoice::create([
            "notes" => $request->notes,
            "num_ticket" => $num_ticket,
            "num_ticket_store" => $num_ticket_store,
            "_created_by" => 1,
            "_workpoint_from" => $_workpoint_from,
            "_workpoint_to" => $_workpoint_to,
            "_type" => $request->_type,
            "printed" => 0,
            "time_life" => "00:15:00",
            "_status" => 1
        ]);
        $this->log(1, $requisition);
        if(isset($data['products'])){ $requisition->products()->attach($data['products']); }

        if($request->_type != 1){ $this->refreshStocks($requisition); }
        $requisition->load(['type',
        'status',
        'products.category.familia.seccion',
        'to',
        'from',
        'created_by',
        'log',
        'products.stocks' => fn($q) => $q->where('_workpoint', $_workpoint_from)]);
            // $this->nextStep($requisition->id);
            return response()->json([
                "success" => true,
                "order" => $requisition
            ]);
    }

    public function getToSupplyFromStore($workpoint_id, $workpoint_to, $seccion = null){ // Función para hacer el pedido de minimos y máximos de la sucursal

        // $workpoint = WorkPoint::find($workpoint_id); // Obtenemos la sucursal a la que se le realizara el pedido
        if($workpoint_id == 1 || $workpoint_id == 2 || $workpoint_id == 22 || $workpoint_id == 24 || $workpoint_id == 16 ){
            $cats= $seccion;
        }else{
            $cats = $this->categoriesByStore($workpoint_id);
        }
        // Obtener todas las categorias que puede pedir la sucursal
        // Todos los productos antes de ser solicitados se válida que haya en CEDIS y la sucursal los necesite en verdad, verificando que la existencia actual sea menor al máximo en primer instancia

        $wkf = $workpoint_id;
        $wkt = $workpoint_to;

        if($workpoint_id == 1 || $workpoint_id == 2 || $workpoint_id == 22 || $workpoint_id == 24 || $workpoint_id == 16){
            $pquery = "SELECT
            P.id AS id,
            P.code AS code,
            P._unit AS unitsupply,
            P.pieces AS ipack,
            P.cost AS cost,
                (SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0) AS stock,
                (SELECT min FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS min,
                (SELECT max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS max,
                SUM(IF(PS._workpoint= 1 , PS.stock, 0)) AS CEDIS,
                (SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) AS PANTACO,
                (SELECT SUM(in_transit) FROM product_stock WHERE _workpoint = $wkf AND _product = P.id) AS transito
            FROM
                products P
                    INNER JOIN product_categories PC ON PC.id = P._category
                    INNER JOIN product_stock PS ON PS._product = P.id
            WHERE
                GETSECTION(PC.id) in ($cats)
                    AND P._status != 4
                    AND (IF(PS._workpoint = $wkt, PS._status, 0)) = 1
                    AND ((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product=P.id AND _status!=4 AND min>0 AND max>0)) IS NOT NULL
                    AND (IF((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product=P.id AND _status!=4 AND min>0 AND max>0) <= (SELECT min FROM product_stock WHERE _workpoint=$wkf AND _product=P.id), (SELECT  max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) - (SELECT  stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0), 0)) > 0
            GROUP BY P.code
            HAVING (SELECT SUM(stock) FROM product_stock WHERE _workpoint = $wkt AND _product = P.id) != 0";

        }else{
            $pquery = "SELECT
            P.id AS id,
            P.code AS code,
            P._unit AS unitsupply,
            P.pieces AS ipack,
            P.cost AS cost,
                (SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0) AS stock,
                (SELECT min FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS min,
                (SELECT max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) AS max,
                SUM(IF(PS._workpoint= 1 , PS.stock, 0)) AS CEDIS,
                (SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) AS PANTACO,
                (SELECT SUM(in_transit) FROM product_stock WHERE _workpoint = $wkf AND _product = P.id) AS transito
            FROM
                products P
                    INNER JOIN product_categories PC ON PC.id = P._category
                    INNER JOIN product_stock PS ON PS._product = P.id
            WHERE
                GETSECTION(PC.id) in ($cats)
                    AND P._status != 4
                    AND (IF(PS._workpoint = $wkt, PS._status, 0)) = 1
                    AND ((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product=P.id AND _status!=4 AND min>0 AND max>0)) IS NOT NULL
                    AND (IF((SELECT stock FROM product_stock WHERE _workpoint=$wkf AND _product=P.id AND _status!=4 AND min>0 AND max>0) <= (SELECT min FROM product_stock WHERE _workpoint=$wkf AND _product=P.id), (SELECT  max FROM product_stock WHERE _workpoint=$wkf AND _product = P.id) - (SELECT  stock FROM product_stock WHERE _workpoint=$wkf AND _product = P.id AND _status != 4 AND min > 0 AND max > 0), 0)) > 0
            GROUP BY P.code";
        }


        $rows = DB::connection('vizapi')->select($pquery);
        $tosupply = [];
        foreach ($rows as $product) {
            $stock = $product->stock;
            $min = $product->min;
            $max = $product->max;
            $transit = $product->transito;
                if($workpoint_id == 1){
                    if( $product->unitsupply==3 ){
                        $required = ($stock<=$min) ? ($max-$stock)-$transit : 0;
                        $ipack = $product->ipack == 0 ? 1 : $product->ipack;
                        $boxes = floor($required/$ipack);

                        ($boxes>=1) ? $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$boxes, "_supply_by"=>3, 'comments'=>'', "stock"=>0 ] : null;
                    }else if( $product->unitsupply==1){
                        $required = ($max-$stock) - $transit;
                        if($required >= 6){
                            ($stock<=$min) ? $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$required,  "_supply_by"=>1 , 'comments'=>'', "stock"=>0] : null ;
                        }

                    }
                }else{
                    if( $product->unitsupply==3 ){
                        $required = ($stock<=$min) ? ($max-$stock)-$transit : 0;
                        $ipack = $product->ipack == 0 ? 1 : $product->ipack;
                        $boxes = floor($required/$ipack);

                        ($boxes>=1) ? $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$boxes, "_supply_by"=>3, 'comments'=>'', "stock"=>0 ] : null;
                    }else if( $product->unitsupply==1){
                        $required = ($max-$stock) -$transit;
                        if($required >= 6){
                            ($stock<=$min) ? $tosupply[$product->id] = [ 'units'=>$required, "cost"=>$product->cost, 'amount'=>$required,  "_supply_by"=>1 , 'comments'=>'', "stock"=>0] : null ;
                        }

                    }
                }
        }

        return ["products" => $tosupply];
    }

    public function categoriesByStore($_workpoint){
        switch($_workpoint){
            case 1: return '"Detalles", "Peluches", "Hogar","Calculadora","Navidad","Papeleria","Juguete","Paraguas","Electronicos","Mochila"'; break;
            case 2: return '"Detalles", "Peluches", "Hogar","Calculadora","Navidad","Papeleria","Juguete","Paraguas","Electronicos","Mochila"'; break;
            case 3: return '"Paraguas"'; break;
            case 4: return '"Mochila"'; break;
            case 5: return '"Mochila"'; break;
            case 6: return '"Calculadora", "Electronicos", "Hogar","Papeleria"'; break;
            case 7: return '"Mochila"'; break;
            case 8: return '"Calculadora", "Juguete", "Papeleria"'; break;
            case 9: return '"Mochila"'; break;
            case 10: return '"Calculadora", "Electronicos", "Hogar","Papeleria"'; break;
            case 11: return '"Juguete"'; break;
            case 12: return '"Juguete"'; break;
            case 13: return '"Mochila"'; break;
            case 17: return '"Calculadora", "Electronicos", "Hogar","Papeleria","Mochila"'; break;//san pablo c
            case 18: return '"Mochila", "Electronico", "Hogar"'; break;
            case 19: return '"Juguete"'; break;
            case 20: return '"Mochila"'; break;
            case 22: return '"Mochila"'; break;
            case 23: return '"Mochila"'; break;
            case 24: return '"Mochila"'; break;
        }
    }

    public function log($case, Invoice $requisition, $_printer=null, $actors=[]){
        $account = AccountVA::find(1);
        $responsable = $account->names.' '.$account->surname_pat;
        $previous = null;

        if($case != 1){
            $logs = $requisition->log->toArray();
            $end = end($logs);
            $previous = $end['pivot']['_status'];
        }

        if($previous){
            $requisition->log()->syncWithoutDetaching([$previous => [ 'updated_at' => new \DateTime()]]);
        }

        switch($case){
            case 1: // LEVANTAR PEDIDO
                $requisition->log()->attach(1, [ 'details'=>json_encode([ "responsable"=>$responsable ]), 'created_at' => carbon::now()->format('Y-m-d H:i:s'), 'updated_at' => carbon::now()->format('Y-m-d H:i:s') ]);
            break;

            case 2: // POR SURTIR => IMPRESION DE COMPROBANTE EN TIENDA
                $port = 9100;
                $requisition->log()->attach(2, [ 'details'=>json_encode([ "responsable"=>$responsable ]), 'created_at' => carbon::now()->format('Y-m-d H:i:s'), 'updated_at' => carbon::now()->format('Y-m-d H:i:s') ]);// se inserta el log dos al pedido con su responsable
                $requisition->_status=2; // se prepara el cambio de status del pedido (a por surtir (2))
                $requisition->save(); // se guardan los cambios
                $requisition->fresh(['log']); // se refresca el log del pedido
                $_workpoint_to = $requisition->_workpoint_to;
                $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where('_workpoint', $_workpoint_to);
                        });
                    }]);
                }]);

                if($requisition->_workpoint_to == 2){
                    $ipprinter = env("PRINTERTEX");
                }else if($requisition->_workpoint_to == 24){
                    $ipprinter = env("PRINTERBOL");
                }else if($requisition->_workpoint_to == 16){
                    $ipprinter = env("PRINTERBRASIL");
                }else{
                    $ipprinter = env("PRINTER_P3") ;
                }

                $miniprinter = new PrinterController();
                $printed_provider = $miniprinter->requisitionTicket($ipprinter,$requisition);

                if($printed_provider){
                    $requisition->printed = ($requisition->printed+1);
                    $requisition->save();
                }else {
                    $groupvi = "120363185463796253@g.us";
                    $mess = "El pedido ".$requisition->id." no se logro imprimir, favor de revisarlo";
                    $this->sendWhatsapp($groupvi, $mess);
                }

            $requisition->refresh('log');

            $log = $requisition->log->filter(function($event) use($case){
                return $event->id >= $case;
            })->values()->map(function($event){
                return [
                    "id" => $event->id,
                    "name" => $event->name,
                    "active" => $event->active,
                    "allow" => $event->allow,
                    "details" => json_decode($event->pivot->details),
                    "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                    "updated_at" => $event->pivot->updated_at->format('Y-m-d H:i')
                ];
            });
            return [
                "success" => (count($log)>0),
                "printed" => $requisition->printed,
                "status" => $requisition->status,
                "log" => $log
            ];
        }
    }

    public function refreshStocks(Invoice $requisition){ // Función para actualizar los stocks de un pedido de resurtido
        $_workpoint_to = $requisition->_workpoint_to;
        $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['stocks' => function($query) use($_workpoint_to){
                $query->where('_workpoint', $_workpoint_to);
            }]);
        }]);
        foreach($requisition->products as $product){
            $requisition->products()->syncWithoutDetaching([
                $product->id => [
                    'units' => $product->pivot->units,
                    'comments' => $product->pivot->comments,
                    'stock' => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0
                ]
            ]);
        }
        return true;
    }

    public function nextStep(Request $request){
        $id = $request->id;
        $requisition = Invoice::with(["to", "from", "created_by"])->find($id);
        $server_status = 200;
        if($requisition){
            $_status = $requisition->_status+1;

            $process = InvoiceStatus::all()->toArray();

            if(in_array($_status, array_column($process, "id"))){
                $result = $this->log($_status, $requisition);
                $msg = $result["success"] ? "" : "No se pudo cambiar el status";
                $server_status = $result ["success"] ? 200 : 500;
            }else{
                $msg = "Status no válido";
                $server_status = 400;
            }
        }else{
            $msg = "Pedido no encontrado";
            $server_status = 404;
        }
        return response()->json([
            "success" => isset($result) ? $result["success"] : false,
            "serve_status" => $server_status,
            "msg" => $msg,
            "requisition"=>$requisition,
            "updates" =>[
                "status" => isset($result) ? $result["status"] : null,
                "log" => isset($result) ? $result["log"] : null,
                "printed" =>  isset($result) ? $result["printed"] : null
            ]
        ]);
    }
//EL OTRO
    public function getCedis(){
        $cedis = WorkPointVA::where([['active',1]])->get();
        return response()->json($cedis,200);
    }

    public function getSeccion(Request $request){
        $sid = $request->route('sid');
        $type = $request->_type;
        if($type == 1){
            $families = ProductCategoriesVA::where([['alias','!=',null],['deep',0]])
            ->get();
            $locations = CellerVA::where([['_workpoint',$sid]])->get();
            $cellers = $locations->map(function($celler){
                $celler->sections = \App\Models\CellerSectionVA::where([
                    ['_celler', '=',$celler->id],
                    ['deep', '=', 0],
                    ['deleted_at',null]
                ])->get();
                return $celler;
            });
            $res = ['locations'=>$cellers,'sections'=>$families];
            return response()->json($res,200);
        }else if($type == 2){
            $families = ProductCategoriesVA::with('familia.seccion')->where([['alias','!=',null]])
            ->get();
            $res= ['families'=>$families];
            return response()->json($res,200);
        }else{
            $res = [
                "message"=>'No existe el tipo de resurtido',
            ];
            return response()->json($res,200);
        }
    }

    public function createAutomate(Request $request){//creacion de pedido
        $_workpoint_from = $request->workpoint_from;//hacia donde
        $_workpoint_to = $request->workpoint_to;//de donde
        $products = $request->products;
        $supply = $request->supply_by;
        // return $products;
        $data = $this->getToSupplyFromStoreAutomate($products,$supply);

        if(isset($data['msg'])){
            return response()->json([
                "success" => false,
                "msg" => $data['msg']
            ]);
        }

        $now = new \DateTime();
        $num_ticket = Invoice::where('_workpoint_to', $_workpoint_to)
                                    ->whereDate('created_at', $now)
                                    ->count()+1;
        $num_ticket_store = Invoice::where('_workpoint_from', $_workpoint_from)
                                        ->whereDate('created_at', $now)
                                        ->count()+1;

        $requisition =  Invoice::create([
            "notes" => $request->notes,
            "num_ticket" => $num_ticket,
            "num_ticket_store" => $num_ticket_store,
            "_created_by" => $request->id_userviz,
            "_workpoint_from" => $_workpoint_from,
            "_workpoint_to" => $_workpoint_to,
            "_type" => $request->type,
            "printed" => 0,
            "time_life" => "00:15:00",
            "_status" => 1
        ]);
        $this->log(1, $requisition);
        if(isset($data['products'])){ $requisition->products()->attach($data['products']); }

        if($request->_type != 1){ $this->refreshStocks($requisition); }

        $requisition->load('type', 'status', 'products.category.familia.seccion', 'to', 'from', 'created_by', 'log');
            $this->nextStepAutomate($requisition->id);
            return response()->json([
                "success" => true,
                "order" => $requisition
            ]);
    }

    public function nextStepAutomate($id){

        $requisition = Invoice::with(["to", "from", "created_by"])->find($id);
        $server_status = 200;
        if($requisition){
            $_status = $requisition->_status+1;

            $process = InvoiceStatus::all()->toArray();

            if(in_array($_status, array_column($process, "id"))){
                $result = $this->log($_status, $requisition);
                $msg = $result["success"] ? "" : "No se pudo cambiar el status";
                $server_status = $result ["success"] ? 200 : 500;
            }else{
                $msg = "Status no válido";
                $server_status = 400;
            }
        }else{
            $msg = "Pedido no encontrado";
            $server_status = 404;
        }
        return response()->json([
            "success" => isset($result) ? $result["success"] : false,
            "serve_status" => $server_status,
            "msg" => $msg,
            "requisition"=>$requisition,
            "updates" =>[
                "status" => isset($result) ? $result["status"] : null,
                "log" => isset($result) ? $result["log"] : null,
                "printed" =>  isset($result) ? $result["printed"] : null
            ]
        ]);
    }



    public function getToSupplyFromStoreAutomate($products,$supply){ // Función para hacer el pedido de productos de familia

        $tosupply = [];
        foreach ($products as $product) {
                $tosupply[$product['id']] = [ 'units'=>$product['pieces'], "cost"=>$product['cost'], 'amount'=>$product['required'], "_supply_by"=>$supply, 'comments'=>'', "stock"=>0 ];
        }
        return ["products" => $tosupply];
    }


    public function getAssortmentInsumos(Request $request){
        $products = Product::with([
            'category.familia.seccion'
            ])
            ->whereHas('category.familia.seccion', function($query)  { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',[1011]);
            })
            ->where('_status','!=',4)->get();
        return response()->json($products);
    }

    public function impPreview(Request $request){
        $requisition =  $request->all();
        $miniprinter = new PrinterController();
        $printed_provider = $miniprinter->previewRequisition($request->ip_address,$requisition);
        if($printed_provider){
            return response()->json('Impresion Correcta',200);
        }else{
            return response()->json('Impresion Incorrecta',401);
        }
    }

    public function  reportProductsCategories(Request $request){
        // return $request->all();
        $workpoint_to = $request->_workpoint_to;
        $workpoint_from = $request->_workpoint_from;
        $seccion = isset($request->seccion) ? $request->seccion : false;
        $familia = isset($request->familia) ? $request->familia : false;
        $categoria = isset($request->categoria) ? $request->categoria : false;
        $products = ProductVA::with([
            'category.familia.seccion',
            'locations'  => function($query) use($workpoint_from ) {
                $query->whereHas('celler', function($query)use($workpoint_from){
                    $query->where('_workpoint', $workpoint_from );
                });
            },
            'stocks' => function($query) use ($workpoint_from) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2,16,$workpoint_from])->distinct();
            }
        ]);
        if($seccion){
            $products->whereHas('category.familia.seccion', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            });
        }
        if($familia){
            $products->whereHas('category.familia', function($query) use ($familia) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$familia);
            });
        }
        if($categoria){
            $products->whereHas('category', function($query) use ($categoria) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$categoria);
            });
        }
        // $products->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
        //         $query->whereIn('_workpoint', [1, 2, 16])
        //                 ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
        // });
        $result = $products->where('_status','!=',4)->get();
        return response()->json($result);
    }

    public function getProductReportLocations(Request $request){
        // return $request->all();
        $workpoint_to = $request->_workpoint_to;
        $workpoint_from = $request->_workpoint_from;
        $celler = isset($request->celler) ? $request->celler : false;
        $locations = isset($request->locations) ? $request->locations : false;
        if($locations){
            $loc = $this->getAllDescendantLocations($locations);
        }
        $sections = isset($request->section) ? $request->section : false;
        $products = ProductVA::with([
            'category.familia.seccion',
            'locations'  => function($query) use($workpoint_from, $celler ) {
                $query->whereHas('celler', function($query)use($workpoint_from,$celler){
                    $query->where('_workpoint', $workpoint_from );
                    $query->whereIn('id',$celler);
                });
            },
            'stocks' => function($query) use ($workpoint_from) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2, 16,$workpoint_from])->distinct();
            }]);
            if($sections){
                $products->whereHas('category.familia.seccion', function($query) use ($sections) { // Aplicamos el filtro en la relación seccion
                    $query->whereIn('id',$sections);
                });
            }
            if($celler){
                $products->whereHas('locations',function($query) use ($celler)  {
                    $query->whereHas('celler', function($query)use($celler){
                        $query->whereIn('id',$celler );
                    });
                });
            }
            if($loc){
                $products->whereHas('locations',function($query) use ($loc)  {
                    $query->whereIn('id',$loc);
            });
            }
            $res = $products->where('_status','!=',4)->get();
        return response()->json($res);
    }

    public function getAllDescendantLocations($locations, $descendants = []) {
        // Busca los hijos directos
        $children = CellerSectionVA::whereIn('root', $locations)->pluck('id');

        // Si no hay hijos, terminamos
        if ($children->isEmpty()) {
            return $descendants;
        }

        // Agregar hijos encontrados a la lista de descendientes
        $descendants = array_merge($descendants, $children->toArray());

        // Llamada recursiva con los hijos encontrados
        return $this->getAllDescendantLocations($children, $descendants);
    }

    public function getProductReport(Request $request){
        $sid = $request->route('sid');
        $seccion = $request->data;
        $products = ProductVA::with([
            'category.familia.seccion',
            'locations'  => function($query) use($sid ) {
                $query->whereHas('celler', function($query)use($sid){
                    $query->where('_workpoint', $sid );
                });
            },
            'stocks' => function($query) use ($sid) { //Se obtiene el stock de la sucursal
                $query->whereIn('_workpoint',[1,2,$sid])->distinct();
            }])
            ->whereHas('category.familia', function($query) use ($seccion) { // Aplicamos el filtro en la relación seccion
                $query->whereIn('id',$seccion);
            })
            ->whereHas('stocks', function($query) { // Solo productos con stock mayor a 0 en el workpoint
                $query->whereIn('_workpoint', [1, 2])
                        ->where('stock', '>', 0); // Filtra solo aquellos con stock positivo
            })
            ->where('_status','!=',4)->get();
        return response()->json($products);
    }
}
