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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\Transfers;
use App\Models\Warehouses;
use App\Models\WorkpointVA;
use App\Models\ProductStockVA;
use App\Models\CellerSectionVA;

use App\Models\User;
use App\Models\ProductVA;
use App\Models\ProductCategoriesVA;
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
            // 'products.category.familia.seccion',
            // 'products.units',
            // 'products.variants',
            // 'products.stocks' => fn($q) => $q->whereIn('_workpoint', [1,2]),
            // 'products.locations' => fn($q) => $q->whereHas('celler', fn($l) => $l->where('_workpoint', 1))->whereNull('deleted_at')
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
        // return $order;

        foreach ($ubicaciones as $ubicacion) {
            $rootId = $ubicacion['id'];
            $partitionCount = (int) $ubicacion['partition'];
            $productosFiltrados = $order->products->filter(function ($producto) use ($rootId, $productosAsignados) {
                if ($productosAsignados->contains($producto->id)) {
                    return false;
                }
                foreach ($producto->locations as $loc) {
                    $rootNode = $loc->getRootNode();
                    if ($rootNode && $rootNode->id == $rootId) {
                        return true; // Solo si alguna raíz coincide
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
                $reqio = $npartition->load(['status','log','products.locations' => fn($q) => $q->whereHas('celler', fn($l) => $l->where('_workpoint', $toWorkpointId))->whereNull('deleted_at'),'requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
                if($toWorkpointId == 2){
                    $ip = env('PRINTERTEX');
                }else{
                    $ip = env('PRINTER_P3');
                }
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
        $change->_warehouse = $warehouse;
        $change->_status = $status;
        $change->save();
        $freshPartition = $change->load(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log']);
        $products = $freshPartition->products;
        $from = $freshPartition->requisition['from']['id'];
        $transit  = $this->modifyTransit($products,$from);
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

    public function getData(Request $request){
        $cedis = $request->cedis;
        $sucursal= $request->sucursal;
        $fechas = $request->fechas;
        $clente = $sucursal['_client'];
        // $invoicesResponse = Http::timeout(200)->post('192.168.10.160:1619'.'/storetools/public/api/Resources/getInvoices', ['_client'=> $clente, 'fechas'=>$fechas] );
        $invoicesResponse = Http::timeout(500)->post($cedis['ip_address'].'/storetools/public/api/Resources/getInvoices', ['_client'=> $clente, 'fechas'=>$fechas] );

        if($invoicesResponse->status() == 200){
            $unicos = array_values(array_unique(array_map(function($val){return "'".'P-'.$val['PARTICION']."'";},$invoicesResponse->json())));
            $salidas =  implode(',',$unicos);
            // return $salidas;
            // $entriesResponse = Http::timeout(200)->post('192.168.10.160:1619'.'/storetools/public/api/Resources/getEntries',["invoices"=>$salidas]);
            $entriesResponse = Http::timeout(500)->post($sucursal['ip_address'].'/storetools/public/api/Resources/getEntries',["invoices"=>$salidas]);
            $res = [
                "unicos"=>$salidas,
                "salidas"=>json_decode($invoicesResponse),
                "entradas"=>json_decode($entriesResponse),
            ];
            return $res;
        }




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
                'nuevo_path' => 'P1',
                'nuevo_nombre' => 'PISO 1',
                'ids' => [4555, 10627, 305,1115], // IDs de CUARTO, ESCALERAS, MONTACARGAS que deben quedar debajo de PISO 1
            ],
            [
                'nuevo_path' => 'P2',
                'nuevo_nombre' => 'PISO 2',
                'ids' => [339, 457, 538, 573, 913, 10878, 15588, 2478723,246],
            ],
            [
                'nuevo_path' => 'P3',
                'nuevo_nombre' => 'PISO 3',
                'ids' => [588, 666, 792, 926, 958, 10689, 15608],
            ],
            [
                'nuevo_path' => 'P4',
                'nuevo_nombre' => 'PISO 4',
                'ids' => [990, 1046, 1069, 10952, 13813, 19321, 21800, 21801,1092],
            ],
            [
                'nuevo_path' => 'PB',
                'nuevo_nombre' => 'PLANTA BAJA',
                'ids' => [1, 224], // Aquí pones los IDs reales
            ],
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
}
            // $products = $freshPartition->products;
            // $from = $freshPartition->requisition['from']['id'];
            // $transit  = $this->modifyTransitReceived($products,$from);
