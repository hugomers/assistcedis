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

use Carbon\CarbonImmutable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;


class InvoicesController extends Controller
{
    public function index(){
        $stores = WorkpointVA::where('active',1)->get();
        $warehouses = Warehouses::all();
        $res = [
            "stores"=>$stores,
        ];
        return response()->json($res,200);
    }

    public function addInvoice(Request $request){
        $createdBy = $request->created_by;
        $num_ticket = Invoice::where('_workpoint_to', $request->to['id'])
                                    ->whereDate('created_at',now())
                                    ->count()+1;
        $num_ticket_store = Invoice::where('_workpoint_from', $request->from['id'])
                                        ->whereDate('created_at', now())
                                        ->count()+1;
        $requisition = new Invoice;
        $requisition->notes = $request->notes;
        $requisition->num_ticket = $num_ticket;
        $requisition->num_ticket_store = $num_ticket_store;
        $requisition->_created_by = $createdBy['id_va'];
        $requisition->_workpoint_from = $request->from['id'];
        $requisition->_workpoint_to = $request->to['id'];
        $requisition->_type = 1;
        $requisition->printed = 0;
        $requisition->time_life = "00:15:00";
        $requisition->_status = 5;
        $requisition->save();
        $res = $requisition->fresh();
        $log = $this->logInt($res->id,$res->_status);
        if($log){
            $ins = [
                "_requisition"=>$res->id,
                "_suplier_id"=>$createdBy['id'],
                "_suplier"=>$createdBy['complete_name'],
                "_out_verified"=>$createdBy['id'],
                "_status"=>$res->_status
            ];
            $inspart = new partitionRequisition($ins);
            $inspart->save();
            $parti  = $inspart->fresh();
            $partition = partitionRequisition::with(['status','log','products.variants','products.stocks' => fn($q) => $q->whereIn('id',[1,2]),'products.prices','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log'])->find($parti->id);
            $simon = $requisition->load(['type', 'status', 'to', 'from', 'created_by', 'log','partition.status','partition.log','partition.products']);
            $response = [
                "partition"=>$partition,
                "requisition"=>$simon
            ];
            return response()->json($response);
        }else{
            return response()->json('no se inserto la factura',500);
        }


    }

    public function logInt($oid,$moveTo){
            $requisition = Invoice::with(["to", "from", "log", "status", "created_by","partition.status","partition.log","type"])->find($oid);
            $now = CarbonImmutable::now();
            $requisition->log()->attach($moveTo, [ 'details'=>json_encode([ "responsable"=>$requisition->created_by['nick'] ]) ]);
            $requisition->_status=$moveTo;
            $requisition->save();
            $requisition->load(['log','status']);
            return true;
    }

    public function changestate(Request $request){
        try {
            $oid = $request->id;
            $moveTo = $request->_status + 1;
            // $moveTo = 5;

            $requisition = Invoice::with(["to", "from", "log", "status", "created_by","partition.status","partition.log","type"])->find($oid);
            $cstate = $requisition->_status;
            $now = CarbonImmutable::now();
            $prevstate = null;
            if( ($cstate==5&&$moveTo==6)){

                $idParti = $request->partition[0]['id'];
                $partition = partitionRequisition::find($idParti);
                $partition->_status = $moveTo;
                $partition->entry_key = md5($idParti);
                $partition->save();
                $partition->load('requisition.from','requisition.to','status','products.prices','log');
                $partition->verified = $partition->getOutVerifiedStaff();

                $idlog = partitionLog::max('id') + 1;
                $inslo = [
                    'id'=>$idlog,
                    '_requisition'=>$request->id,
                    '_partition'=>$request->partition[0]['id'],
                    '_status'=>$moveTo,
                    'details'=>json_encode(['responsable'=>$partition->getOutVerifiedStaff()->complete_name]),
                ];
                $inslog = partitionLog::insert($inslo);
                $logs = $requisition->log->toArray();
                $end = end($logs);
                $prevstate = $end['pivot']['_status'];
                $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                $requisition->log()->attach($moveTo, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                $requisition->_status=$moveTo; // se actualiza el status del pedido
                $requisition->save(); // se guardan los cambios
                $reqifresh = $requisition->fresh();
                $freshReq = Invoice::with(["to", "from", "log", "status", "created_by","partition.status","partition.log","type"])->find($reqifresh->id);
                $response = [
                    "partition"=>$partition,
                    "requisitiion"=>$freshReq,
                ];
                return response()->json($response);
            }else{ return response()->json("El status $cstate no puede cambiar a $moveTo",400); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function getInvoice($inv){
        $invoice = Invoice::find($inv);
        if($invoice){
            if($invoice->_status == 5){
                $invoice->load(["to", "from", "log", "status", "created_by","partition.status","partition.log","type","products.stocks"=> fn($q) => $q->where('id',1)]);
                return response()->json($invoice);
            }else{
                return response()->json('La Factura ya se realizo',401);
            }
        }else{
            return response()->json('No se encuentra la factura',404);
        }
    }

    public function addProduct(Request $request){
        $productData = [
            $request->_product => [
                'amount' => $request->amount,
                '_supply_by' => $request->_supply_by,
                'units' => $request->units,
                'cost' => $request->cost,
                'total' => $request->total,
                'comments' => '',
                'stock' => $request->stock,
                'toDelivered' => $request->toDelivered,
                'ipack' => $request->ipack,
                'checkout' => $request->checkout,
                '_suplier_id' => $request->_suplier_id,
                '_partition' => $request->_partition
            ]
        ];
        $invoice =  Invoice::find($request->_requisition);
        $invoice->products()->syncWithoutDetaching($productData);
        $productAdded = $invoice->products()->with(['stocks'=> fn($q) => $q->where('id',1)])->where("id", $request->_product)->first();
        return response()->json($productAdded);
    }

    public function editProduct(Request $request){
        $invoice = Invoice::findOrFail($request->_requisition);
        $pivotData = [
            'amount' => $request->amount,
            '_supply_by' => $request->_supply_by,
            'units' => $request->units,
            'cost' => $request->cost,
            'total' => $request->total,
            'comments' => $request->comments ?? '',
            'stock' => $request->stock,
            'toDelivered' => $request->toDelivered,
            'ipack' => $request->ipack,
            'checkout' => $request->checkout,
            '_suplier_id' => $request->_suplier_id,
            '_partition' => $request->_partition,
        ];
        $invoice->products()->updateExistingPivot($request->_product, $pivotData);
        $productUpdated = $invoice->products()->with(['stocks'=> fn($q) => $q->where('id',1)])->where("id", $request->_product)->first();
        return response()->json($productUpdated);
    }

    public function deleteProduct(Request $request){
        $requisition = Invoice::find($request->_requisition);
        $requisition->products()->detach([$request->_product]);
        return response()->json(["success" => true]);
    }

    public function addInvoiceFS(Request $request){
        try {
            $ip = $request->requisition['to']['dominio'];
            $addInvoice = HTTP::post($ip.'/storetools/public/api/invoice/addInvoice',$request->all());
            $status = $addInvoice->status();
            if($status == 201){
                $folio = $addInvoice->json();
                $partition = partitionRequisition::find($request->id);
                $partition->invoice = $folio['folio'];
                $partition->save();
                return response()->json($folio['folio'],200);
            }else{
                return response()->json($addInvoice->json(),$addInvoice->status());
            }
        } catch (ConnectionException $e) {
            return response()->json([
            'error' => 'No se pudo conectar con el servidor remoto: ' . $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
            'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 503);
        }
    }

    public function addTransferFS(Request $request){
        try {
            $ip = $request->requisition['to']['dominio'];
            $addInvoice = HTTP::post($ip.'/storetools/public/api/invoice/addTransfer',$request->all());
            $status = $addInvoice->status();
            if($status == 201){
                $folio = $addInvoice->json();
                $partition = partitionRequisition::find($request->id);
                $partition->invoice = $folio['folio'];
                $partition->save();
                return response()->json($folio['folio'],200);
            }else{
                return response()->json($addInvoice->json(),$addInvoice->status());
            }
        } catch (ConnectionException $e) {
            return response()->json([
            'error' => 'No se pudo conectar con el servidor remoto: ' . $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
            'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 503);
        }
    }

    public function endTransferFS(Request $request){
        try {
            $ip = $request->requisition['from']['dominio'];
            $addInvoice = HTTP::post($ip.'/storetools/public/api/invoice/endTransfer',$request->all());
            $status = $addInvoice->status();
            if($status == 201){
                $folio = $addInvoice->json();
                $partition = partitionRequisition::find($request->id);
                $partition->invoice_received = $folio['folio'];
                $partition->save();
                return response()->json($folio['folio'],200);
            }else{
                return response()->json($addInvoice->json(),$addInvoice->status());
            }
        } catch (ConnectionException $e) {
            return response()->json([
            'error' => 'No se pudo conectar con el servidor remoto: ' . $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
            'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 503);
        }
    }

    public function addEntryFS(Request $request){
        try {
            $ip = $request->requisition['from']['dominio'];
            $addInvoice = HTTP::post($ip.'/storetools/public/api/invoice/addEntry',$request->all());
            $status = $addInvoice->status();
            if($status == 201){
                $folio = $addInvoice->json();
                $partition = partitionRequisition::find($request->id);
                $partition->invoice_received = $folio['folio'];
                $partition->save();
                return response()->json($folio['folio'],200);
            }else{
                return response()->json($addInvoice->json(),$addInvoice->status());
            }
        } catch (ConnectionException $e) {
            return response()->json([
            'error' => 'No se pudo conectar con el servidor remoto: ' . $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
            'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 503);
        }
    }

    public function indexDashboard(Request $request){
            $fechas = $request->date;
            $now = CarbonImmutable::now();
            if(isset($fechas['from'])){
                $from = $fechas['from'];
                $to = $fechas['to'];
            }else{
                $from = $fechas;
                $to = $fechas;
            }
            $resume = [];
            $dates = [
                $from,
                $to
            ];

            $query = Invoice::with(['type', 'status', 'to', 'from', 'created_by', 'log', 'partition.status', 'partition.log'])
                ->withCount(["products"])
                ->whereBetween(DB::raw('DATE(created_at)'),[$from,$to])->where(function ($q2) use ($request) {
                        $q2->where('_workpoint_to', $request->storeTo)
                        ->orWhere('_workpoint_from', $request->storeTo); // ejemplo adicional
                })->get();
            // $partitions =partitionRequisition::with(['status','log','products','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log'])->whereHas('requisition',function ($q) use($dates,$request)  {$q->whereBetween(DB::raw('DATE(created_at)'),$dates)->where('_workpoint_to',$request->storeTo); })->get();

            $partitions =partitionRequisition::with(['status','log','products','products.prices','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log'])
                ->whereHas('requisition',function ($q) use($dates,$request)  {
                    $q->whereBetween(DB::raw('DATE(created_at)'),$dates)
                    ->where(function ($q2) use ($request) {
                        $q2->where('_workpoint_to', $request->storeTo)
                        ->orWhere('_workpoint_from', $request->storeTo); // ejemplo adicional
                }); })->get();
            foreach ($partitions as $partition) {
                $partition->verified = $partition->getOutVerifiedStaff();
                $partition->receipt  = $partition->getCheckStaff();
                $partition->driving  = $partition->getOutDrivingStaff();
            }
            $pdss = DB::connection('vizapi')->select(
                    "SELECT
                        COUNT(PS._product) as total
                    FROM product_stock PS
                    WHERE
                        PS._status=1 AND
                        PS._workpoint=1 AND
                        PS.stock=0 AND (SELECT sum(stock) FROM product_stock WHERE _workpoint=2 and _product=PS._product)=0; ");

            $pndcs = DB::connection('vizapi')->select("
                    SELECT
                        COUNT(*) AS total
                    FROM products P
                        INNER JOIN product_stock PS ON PS._product = P.id AND PS._workpoint IN (1)
                        LEFT JOIN product_status S ON S.id = PS._status AND PS._workpoint = 1
                        INNER JOIN product_categories PC ON PC.id = P._category
                    WHERE
                        PS._status NOT IN (1,4)
                        AND
                        P._status != 4 AND ((SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) +  PS.stock ) > 0");

            $resume[] = [ "key"=>"pdss", "name"=>"Productos disponibles sin stock", "total"=>$pdss[0]->total ];
            $resume[] = [ "key"=>"pndcs", "name"=>"Productos no disponibles con stock", "total"=>$pndcs[0]->total ];

            $printers = WorkPointVA::with("printers")->where("id",$request->storeTo)->get();

            $users = User::with('staff')->where('_store',$request->storeTo)->whereIn('_rol',[1,2,4,15,16])->get();

            return response()->json([
                "orders"=>$query,
                "from"=>$from,
                "to"=>$to,
                "resume"=>$resume,
                "printers"=>$printers,
                "staff"=>$users,
                "partitions"=>$partitions
            ]);
        // } catch (\Error $e) { return response()->json($e,500); }
    }

    public function order(Request $request){
        $id = $request->route("oid");

        try {
            $order = Invoice::with([
                        'type',
                        'status',
                        'log',
                        'from',
                        'to',
                        'partition.status',
                        'partition.log',
                        'partition.products',
                        'products' => function($query){
                                            $query->selectRaw('
                                                        products.*,
                                                        getSection(products._category) AS section,
                                                        getFamily(products._category) AS family,
                                                        getCategory(products._category) AS category
                                                    ')
                                                    ->with([
                                                        'units',
                                                        'variants',
                                                        'stocks' => function($q){ return $q->whereIn('_workpoint', [1,2]); },
                                                        'locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),
                                                    ]);
                                        }
                    ])->findOrFail($id);

            return response()->json($order);
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function printforsupply(Request $request){
        $ip = $request->ip;
        $port = $request->port;
        $order = $request->order;
        $requisition = Invoice::find($order);

        $_workpoint_to = $requisition->_workpoint_to;

        $requisition->load(['log', 'products' => function($query) use ($_workpoint_to){
            $query->with(['locations' => function($query)  use ($_workpoint_to){
                $query->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where('_workpoint', $_workpoint_to);
                });
            }]);
        }]);
        // return $requisition;
        $printer = new PrinterController();
        $printed = $printer->requisitionTicket($ip, $requisition);

        if($printed){
            $requisition->printed = $requisition->printed +1;
            $requisition->save();
        }

        return response()->json($printed);
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
                });
            }]);
        }]);
        // return $requisition;
        $cellerPrinter = new PrinterController();
        $cellerPrinter;
        $res = $cellerPrinter->PartitionTicket($ip, $requisition);
        return response()->json(["success" => $res, "printer" => $ip]);
    }

    public function changestateRequisition(Request $request){
        try {
            $oid = $request->id;
            $moveTo = $request->state;
            $requisition = Invoice::with(["to", "from", "log", "status", "created_by","partition.status","partition.log","type"])->find($oid);
            $cstate = $requisition->_status;
            $now = CarbonImmutable::now();
            $prevstate = null;

            /**
             * mover de "POR SURTIR (2)" a "SURTIENDO (3)" ||
             * mover de "SURTIENDO (3)" a "Por Enviar (6)"
             *
            */
            if(($cstate==2&&$moveTo==3) || ($cstate==3&&$moveTo==4) || ($cstate==4&&$moveTo==5) || ($cstate==5&&$moveTo==6) || ($cstate==6&&$moveTo==7) || ($cstate==7&&$moveTo==8) || ($cstate==8&&$moveTo==9) || ($cstate==9&&$moveTo==10)  || ($cstate==2&&$moveTo==100)){
                $logs = $requisition->log->toArray();
                $end = end($logs);
                $prevstate = $end['pivot']['_status'];
                $prevstate ? $requisition->log()->syncWithoutDetaching([$prevstate => [ 'updated_at' => $now->format("Y-m-m H:m:s")]]) : null;
                $requisition->log()->attach($moveTo, [ 'details'=>json_encode([ "responsable"=>"VizApp" ]) ]);
                $requisition->_status=$moveTo; // se actualiza el status del pedido
                $requisition->save(); // se guardan los cambios
                $requisition->load(['log','status']); // se refresca el log del pedido
                return response()->json($requisition);
            }else{ return response()->json("El status $cstate no puede cambiar a $moveTo",400); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function orderFresh(Request $request){
        $id = $request->route("oid");
        $order = Invoice::with(['type', 'status', 'to', 'from', 'created_by', 'log','partition.status','partition.log',
        'partition.products'])
            ->withCount(["products"])
            ->find($id);

        return response()->json(["oid"=>$id,"order"=>$order]);
    }

    public function partitionFresh(Request $request){
        $id = $request->route("pid");
        $order = partitionRequisition::with(['status','log','products.variants','products.stocks' => fn($q) => $q->whereIn('id',[1,2]),'products.locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),'products.prices','requisition.type','requisition.status','requisition.to','requisition.from','requisition.created_by','requisition.log'])
        ->find($id);
        $order->verified = $order->getOutVerifiedStaff();
        $order->receipt  = $order->getCheckStaff();
        $order->driving  = $order->getOutDrivingStaff();

        return response()->json(["oid"=>$id,"order"=>$order]);
    }

    public function setdelivery(Request $request){
        $oid = $request->order;
        $product = $request->product;
        $delivery = $request->delivery;
        $ipack = $request->ipack;
        $checkout = $request->checkout;


        $requisition = partitionRequisition::findOrFail($oid);
        $cstate = $requisition->_status;

        $updateCols = $checkout ? [ "toDelivered"=>$delivery, "ipack"=>$ipack, "checkout"=>1 ] : [ "toDelivered"=>$delivery, "ipack"=>$ipack ];

        if($cstate ==3 || $cstate ==4 || $cstate == 5 || $cstate ==9 ){
            $setted = InvoiceBodies::where([ ["_partition",$oid],["_product",$product] ])->update($updateCols);

            return response()->json([
                "order" => $requisition,
                "product" => $product,
                "setted" => $setted
            ]);
        }else{ return response("El status actual de esta orden no permite modificaciones (orderState: $cstate)",400); }
    }

    public function setreceived(Request $request){
        $oid = $request->order;
        $product = $request->product;
        $received = $request->received;
        $ipack = $request->ipack;
        $checkout = $request->checkout;

        $requisition = partitionRequisition::findOrFail($oid);

        $cstate = $requisition->_status;

        $updateCols = [ "toReceived"=>$received ];


        if($cstate==9){
            $setted =InvoiceBodies::where([ ["_partition",$oid],["_product",$product] ])
                    ->update($updateCols);


            return response()->json([
                "order" => $requisition,
                "product" => $product,
                "setted" => $setted
            ]);
        }else{ return response("El status actual de esta orden no permite modificaciones (orderState: $cstate)",400); }
    }

    public function newinvoice(Request $request){
        $oid = $request->route("oid");
        $idParti = $oid;
        $partition = partitionRequisition::with()->find($idParti);
        // $partition->_status = 6;;
        $partition->load('requisition.from','requisition.to','status','products.prices','log');
        $partition->verified = $partition->getOutVerifiedStaff();
        return response()->json(["requisition"=>$partition]);
    }

    public function newTransfer(Request $request){
        $oid = $request->route("oid");
        $idParti = $oid;
        $partition = partitionRequisition::find($idParti);
        // $partition->_status = 6;
        $partition->entry_key = md5($idParti);
        $partition->save();
        $partition->load('requisition.from','requisition.to','status','products.prices','log');
        $partition->verified = $partition->getOutVerifiedStaff();
        return response()->json(["requisition"=>$partition]);
    }

    public function checkin(Request $request){
        try {
            $oid = $request->oid;
            $key = $request->key;

            $order = partitionRequisition::with([
                        // 'type',
                        'status',
                        // 'log',
                        'requisition.from',
                        'requisition.to',
                        'products' => function($query){
                            $query->selectRaw('
                                        products.*,
                                        getSection(products._category) AS section,
                                        getFamily(products._category) AS family,
                                        getCategory(products._category) AS category
                                    ')->with([
                                        'units',
                                        'variants',
                                        'stocks' => function($q){ return $q->whereIn('_workpoint', [1,2]); },
                                        // 'locations' => fn($qq) => $qq->whereHas('celler', function($qqq){ $qqq->where('_workpoint', 1); }),
                                    ]);
                            }
                    ])->where([ ["_requisition",$oid],["entry_key", $key]])->first();

            if($order){
                return response()->json([ "order"=>$order ]);
            }else{ return response("Sin coincidencias para el folio o llave invalida!",404); }
        } catch (\Error $e) { return response()->json($e,500); }
    }

    public function report(Request $request){
        $rep = $request->route("rep");

        switch ($rep) {
            case'pdss':$rows=DB::connection('vizapi')->select(
                    "SELECT
                        P.id AS ID,
                        P.code AS CODIGO,
                        P.description AS DESCRIPCION,
                        GETSECTION(PC.id) AS SECCION,
                        GETFAMILY(PC.id) AS FAMILIA,
                        GETCATEGORY(PC.id) AS CATEGORIA,
                        S.name AS ESTADO
                    FROM products P
                        INNER JOIN product_stock PS ON PS._product = P.id
                        INNER JOIN product_status S ON S.id = PS._status
                        INNER JOIN product_categories PC ON PC.id = P._category
                    WHERE PS._status = 1 AND PS._workpoint = 1 AND (SELECT sum(stock) FROM product_stock WHERE _workpoint = 2 and _product = P.id) = 0  AND (SELECT sum(stock) FROM product_stock WHERE _workpoint = 1 and _product = P.id) = 0;");
                    $name = "Productos disponibles sin stock";
                    $key = "pdss";
                break;

            case'pndcs':$rows=DB::connection('vizapi')->select(
                    "SELECT
                        P.id AS ID,
                        P.code AS CODIGO,
                        P.description AS DESCRIPCION,
                        GETSECTION(PC.id) AS SECCION,
                        GETFAMILY(PC.id) AS FAMILIA,
                        GETCATEGORY(PC.id) AS CATEGORIA,
                        S.name AS ESTADO,
                        PS.stock AS CEDIS,
                        (SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) AS PANTACO
                    FROM products P
                        INNER JOIN product_stock PS ON PS._product = P.id AND PS._workpoint IN (1)
                        LEFT JOIN product_status S ON S.id = PS._status AND PS._workpoint = 1
                        INNER JOIN product_categories PC ON PC.id = P._category
                    WHERE
                        PS._status NOT IN (1,4)
                        AND
                        P._status != 4 AND ((SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) +  PS.stock ) > 0");
                    $name = "Productos no disponibles con stock";
                    $key = "pndcs";
                break;

            default: break;
        }

        return response([ "rows"=>$rows, "name"=>$name, "key"=>$key ]);
    }

    public function massaction(Request $request){
        $action = $request->action;

        switch ($action) {
            case 'pndcs':
                $query = 'UPDATE product_stock PS
                    LEFT JOIN (
                    SELECT _product, SUM(stock) AS pantaco_stock
                    FROM product_stock
                    WHERE _workpoint = 2
                    GROUP BY _product
                    ) P2 ON P2._product = PS._product
                    SET PS._status = 1
                    WHERE PS._status NOT IN (1,4)
                    AND PS._workpoint IN (1,2)
                    AND (IFNULL(P2.pantaco_stock, 0) + PS.stock) > 0;';
                break;

            case 'pdss':
                $query = 'UPDATE product_stock CED
                            INNER JOIN product_stock PAN ON CED._product = PAN._product
                            SET CED._status=3
                            WHERE CED._status=1 AND CED._workpoint=1 AND CED.stock=0 AND PAN.stock=0 AND PAN._workpoint=2;';
                break;
        }
        $q = DB::connection('vizapi')->update($query);
        return response()->json([ "msg"=>"Making $action", "query"=>$query, "exec"=>$q ]);
    }
    public function correction(Request $request){
        $response = [
            "Eliminar"=>[
                'success'=>false,
                'message'=>null,
            ],
            "Salida"=>[
                'success'=>false,
                'message'=>null,
            ],
            "Entrada"=>[
                'success'=>false,
                'message'=>null,
            ],
        ];
        $originalIndexed = [];
        $deletedProducts = [];
        $changedToReceived = [];
        $changedToDelivered = [];
        // return $request->all();

        $partition = partitionRequisition::with(["requisition.from","products"])->find($request->id);
        $to = $partition->requisition->to->dominio;
        $from = $partition->requisition->from->dominio;
        // $to = '192.168.10.160:1619';
        // $from = '192.168.10.160:1619';
        if($partition){
            $produtsOri = $partition->products->toArray();
            $productDes = $request->products;
            foreach ($produtsOri as $product) {
                $originalIndexed[$product['id']] = [
                '_product' => $product['id'],
                'code'=>$product['code'],
                '_requisition'=>$partition->_requisition,
                'toReceived' => $product['pivot']['toReceived'],
                'invoice' => $partition->invoice,
                'pivot'=>$product['pivot']
            ];
            }
            foreach ($productDes as $product) {
                $id = $product['id'];
                if (isset($originalIndexed[$id])) {
                    $originalPivot = $originalIndexed[$id]['pivot'];
                    $modifiedPivot = $product['pivot'];
                    if ($originalPivot['toReceived'] != $modifiedPivot['toReceived']) {
                        $changedToReceived[] = [
                            '_product' => $id,
                            'code'=>$product['code'],
                            '_supply_by'=> $modifiedPivot['_supply_by'],
                            'pxc' => $product['pieces'],
                            'toReceived' => $modifiedPivot['toReceived'],
                            'invoice' => $partition->invoice_received,
                            '_requisition'=>$partition->_requisition
                        ];
                    }
                    if ($originalPivot['toDelivered'] != $modifiedPivot['toDelivered']) {
                        $changedToDelivered[] = [
                            '_product' => $id,
                            'code'=>$product['code'],
                            '_supply_by'=> $modifiedPivot['_supply_by'],
                            'pxc' => $product['pieces'],
                            'toDelivered' => $modifiedPivot['toDelivered'],
                            'invoice' => $partition->invoice,
                            '_requisition'=>$partition->_requisition
                        ];
                    }
                    unset($originalIndexed[$id]);
                }
            }

            $deletedProducts = array_values($originalIndexed);
            if(count($deletedProducts) > 0){//eliminar productos solo en salida
                $eliminar = $this->DeleteProdAccess($to,$deletedProducts);
                if($eliminar['success']){
                    foreach($deletedProducts as $delete){
                        $requisition = Invoice::find($delete['_requisition']);
                        $requisition->products()->detach([$delete['_product']]);
                    }
                    $response['Eliminar']['message'] = $eliminar['message'];
                    $response['Eliminar']['success'] = true;

                }
                $response['Eliminar']['message']= $eliminar['message'];
            }

            if(count($changedToDelivered) > 0){//cambiar total solo en salida
                $ModDelivered = $this->ChangeDelivered($to,$changedToDelivered);
                if($ModDelivered['success']){
                    foreach($changedToDelivered as $delivered){
                        $requisition = Invoice::find($delivered['_requisition']);
                        $requisition->products()->updateExistingPivot($delivered['_product'], ['toDelivered' =>  $delivered['toDelivered']]);
                    }
                    $response['Salida']['message'] = $ModDelivered['message'];
                    $response['Salida']['success'] = true;
                }
                $response['Salida']['message'] = $ModDelivered['message'];
            }

            if(count($changedToReceived) > 0){//cambiar total solo en entrada
                $ModReceived = $this->ChangeReceived($changedToReceived, $from);
                if($ModReceived['success']){
                    foreach($changedToReceived as $received){
                        $requisition = Invoice::find($received['_requisition']);
                        $requisition->products()->updateExistingPivot($received['_product'], ['toReceived' =>  $received['toReceived']]);
                    }
                    $response['Entrada']['message'] = $ModReceived['message'];
                    $response['Entrada']['success'] = true;
                }
                $response['Entrada']['message'] = $ModReceived['message'];
            }
            $result = [
                'toReceived' => $changedToReceived,
                'toDelivered' => $changedToDelivered,
                'deleted' => $deletedProducts,
                'res' => $response,
            ];
            return response()->json($result,200);
        }else{
            return response()->json('No se encontro la particion',400);
        }
    }
    public function DeleteProdAccess($ip,$products){
        try {
            $delProduct = HTTP::post($ip.'/storetools/public/api/Modification/deleteProduct',$products);
            if($delProduct->successful()){
                return ['message'=>$delProduct->json(), 'success'=>true];
            }else{
                return ['message'=>'Sin Conexion', 'success'=>false];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
            'success' => false,
            'message' => 'Sin conexión en el servidor'
            ];
        } catch (\Exception $e) {
            return [
            'success' => false,
            'message' => 'Error inesperado: ' . $e->getMessage()
        ];
        }
    }

    public function ChangeDelivered($ip,$products){
        try {
            $changeDelivered = HTTP::timeout(5)->post($ip.'/storetools/public/api/Modification/changeDelivered',$products);
            // return $changeDelivered->successful();
            if($changeDelivered->successful()){
                return ['message'=>$changeDelivered->json(), 'success'=>true];
            }else{
                return ['message'=>'No se modifico el producto', 'success'=>false];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Sin conexión en el servidor'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage()
            ];
        }
    }

    public function ChangeReceived($products,$ip){
                try {
        $changeReceived = HTTP::post($ip.'/storetools/public/api/Modification/changeReceived',$products);
        if($changeReceived->successful()){
            return ['message'=>$changeReceived->json(), 'success'=>true];
        }else{
            return ['message'=>'Sin Conexion', 'success'=>false];
        }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'Sin conexión en el servidor'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage()
            ];
        }
    }

    public function getStoresAutomate(){
        $stores = WorkpointVA::where('active',1)->get();
        $categories = ProductCategoriesVA::where([['deep',0],['alias','!=',null]])->get();
        $res  = [
            "stores"=>$stores,
            "sections"=>$categories
        ];
        return response()->json($res,200);
    }
}
