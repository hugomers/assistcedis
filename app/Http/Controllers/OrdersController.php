<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SeasonsVA;
use App\Models\AccountVA;
use App\Models\OrderVA;
use App\Models\OrderProcessVA;
use App\Models\OrderProcessConfigVA;
use App\Models\CashRegisterVA;
use App\Models\ClientVA;
use App\Models\PrinterVA;
use App\Models\OrderLogVA;
use App\Models\ProductOrderedVA;
use App\Models\ProductUnitVA;
use App\Models\Invoice;
use App\Models\InvoiceBodies;
use App\Models\partitionRequisition;
use App\Models\partitionLog;
use Carbon\CarbonImmutable;
use Carbon\Carbon;


class OrdersController extends Controller
{
    // public function getOrder($ord){
    //     $order = DB::connect('vizapi')->table('orders')->where('id',$ord)->get();
    //     return response($order);
    // }

    public function getRules(){
        $rules = SeasonsVA::with('rules')->get();
        return response()->json($rules);
    }

    public function getOrder(Request $request){
        $uid = $request->uid;
        $ord = $request->route('ord');
        $ip = $request->ip();
        $user = AccountVA::find($uid);
        if (!$user) {
            return response()->json('Usuario no encontrado', 404);
        }
        $order = OrderVA::find($ord);
        if (!$order) {
            return response()->json('Pedido no encontrado', 404);
        }
        if ($order->_status == 5) {
            $order->_status = 7;
            $order->save();
            $res = $order->fresh();
            $this->createLog($res->id, 7, ['user' => $user, 'ip' => $ip], 'App\User', $user->id);
            return response()->json($res);
        } else {
            return response()->json('El pedido ya está verificado', 401);
        }
    }

    public function getOrderPrv(Request $request){
        $id = $request->pedido;
        $store = $request->store;
        $uid = $request->uid;
        $order = OrderVA::find($id);
        if($order){
            if($order->_workpoint_from == $store){
                if($order->_created_by == $uid){
                    $order = $order->load(['products.category.familia.seccion','products.prices','products.units','client']);
                    return response()->json($order,200);
                }else{
                    return response()->json(['mssg'=>'No creaste tu el pedido'],401);
                }
            }else{
                return response()->json(['mssg'=>'No perteneces a la surusal'],401);
            }
        }else{
            return response()->json(['mssg'=>'No se encuentra el pedido'],404);
        }



    }

    public function getOrderVerify($ord){
        $rules = SeasonsVA::with('rules')->get();
        $order = OrderVA::find($ord);
        if($order->_status == 7){
            $order->load(['products.category.familia.seccion','client','created_by','products.prices']);

            $res = [
                "order"=>$order,
                "rules"=>$rules,
            ];
            return response()->json($res);
        }else{
            return response()->json('El pedido no esta en estado de verificacion', 401);
        }
    }

    public function getOrderAdd(Request $request){
        $oid = $request->oid;
        $uid = $request->uid;
        $ord = $request->route('ord');
        $ip = $request->ip();
        $user = AccountVA::find($uid);
        if (!$user) {
            return response()->json('Usuario no encontrado', 404);
        }
        $order = OrderVA::find($ord);
        if($order){
            if($order->_status == 5){
                $order->_status = 100;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 100, ['user' => $user, 'ip' => $ip, 'message'=>'Cancelado por verificacion actual',"order"=>$oid], 'App\User', $user->id);

                return response()->json($res);
            }else{
                return response()->json('El pedido no esta preparado para la verificacion', 401);
            }
        }else{
            return response()->json('No se encuentra ningun pedido con ese id',404);
        }

    }

    public function editProduct(Request $request){
        $product = $request->all();
        $upd = [
            "amount"=>$product['amount'],
            "price"=>$product['price'],
            "total"=>$product['total'],
            "units"=>$product['units'],
            "_price_list"=>$product['_price_list'],
            "_supply_by"=>$product['_supply_by'],
            "comments"=>isset($product['comments']) ? $product['comments'] : null
        ];
        $update = ProductOrderedVA::where([['_order',$product['_order']],['_product',$product['_product']]])->update($upd);
        return response()->json($update,200);
    }
    public function addProduct(Request $request){
        $product = $request->all();
        $nwProduct = new ProductOrderedVA;
        $nwProduct->kit = '';
        $nwProduct->_product = $product['_product'];
        $nwProduct->_order = $product['_order'];
        $nwProduct->amount = $product['amount'];
        $nwProduct->amountDelivered = $product['amountDelivered'];
        $nwProduct->price = $product['price'];
        $nwProduct->toDelivered = $product['toDelivered'];
        $nwProduct->total = $product['total'];
        $nwProduct->units = $product['units'];
        $nwProduct->_price_list = $product['_price_list'];
        $nwProduct->_supply_by = $product['_supply_by'];
        $nwProduct->comments = isset($product['comments']) ? $product['comments'] : null  ;
        $res = $nwProduct->save();
        return response()->json($res,200);
    }

    public function deleteProduct(Request $request){
        $product = $request->all();
        $delete = ProductOrderedVA::where([['_order',$product['_order']],['_product',$product['_product']]])->delete();
        return response()->json($delete,200);
    }
    public function updateProductPrices(Request $request){
        $products = $request->all();
        foreach ($products as $product) {
            $upd = [
                "_price_list"=>$product['_price_list'],
                "price"=>$product['price'],
                "total"=>$product['total'],
            ];
            $update = ProductOrderedVA::where([['_order',$product['_order']],['_product',$product['_product']]])->update($upd);
        }

        return response()->json($products);
    }

    public function getClient(Request $request){
        $val = $request->val;
        $buscar = ClientVA::where(function($query) use ($val) {
        $query->where('id', 'like', '%' . $val . '%')
              ->orWhere('name', 'like', '%' . $val . '%')
              ->orWhere('phone', 'like', '%' . $val . '%')
              ->orWhere('store_name', 'like', '%' . $val . '%');
        })
        ->where(function($query) {
            $query->where('id', 0)
                ->orWhere('id', '>=', 36);
        })
        ->get();
        if($buscar->isEmpty()){
            $buscar = ClientVA::where('id', 0)->get();
        }

        return response()->json($buscar,200);
    }

    public function changeClientOrder(Request $request){
        $order = $request->order;
        $client = $request->client;

        $updOrder = OrderVA::find($order);
        $updOrder->_client = $client;
        $res = $updOrder->save();
        return response()->json($res);
    }
    public function nextState(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status == 7){
                $order->_status = 8;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 8, ['user' => $user, 'ip' => $ip,"order"=>$oid], 'App\User', $user->id);
                return response()->json($res);
            }else{
                return response()->json('El pedido aun no esta validado',401);
            }
        }else{
            return response()->json('No hay ninguna Order con ese numero',404);
        }
    }

    public function nextStateFinish(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status == 9){
                $order->_status = 10;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 10, ['user' => $user, 'ip' => $ip,"order"=>$oid], 'App\User', $user->id);
                return response()->json($res);
            }else{
                return response()->json('El pedido aun no esta validado',401);
            }
        }else{
            return response()->json('No hay ninguna Order con ese numero',404);
        }
    }

    public function getOrderCash(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status == 8){
                $order->_status = 9;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 9, ['user' => $user, 'ip' => $ip], 'App\User', $user->id);
                return response()->json($res);
            }else{
                return response()->json('El pedido no se puede cobrar',401);
            }
        }else{
            return response()->json('No hay ninguna Order con ese numero',404);
        }
    }

    public function createLog($_order, $_status, $details, $_type, $user){
        $log = new OrderLogVA;
        $log->_order = $_order;
        $log->_status = $_status;
        $log->details = json_encode($details);
        $log->_type = $_type;
        $log->_responsable = $user;
        $log->save();
        return $log;
    }

    public function create(Request $request){
        try{
            $order = DB::transaction( function () use ($request){
                $now = new \DateTime();
                $_parent = null;
                $parent = null;
                if(isset($request->_anex) && $request->_anex){
                    $parent = OrderVA::find($request->_anex);
                    if(!$parent){
                        return response()->json(["success" => false, "server_status" => 404, "msg" => "No se encontro el pedido"]);
                    }else{
                        $_parent = $parent->_order ? $parent->_order : $parent->id;
                    }
                }


                $num_ticket = OrderVA::where('_workpoint_from', $request->_workpoint)->whereDate('created_at', $now)->count()+1;
                $client = 0;
                $order = OrderVA::create([
                    // 'created_at'=>  $now,
                    'num_ticket' => $num_ticket,
                    'name' => $parent ? $parent->name  :  $request->name,
                    '_client' => $client,
                    '_price_list' => 1,
                    '_created_by' => $request->_created_by,
                    '_workpoint_from' => $request->_workpoint,
                    'time_life' => '00:30:00',
                    '_status' => 1,
                    '_order' => $_parent ? $_parent : null
                ]);

                $this->log(1, $order, $request->_created_by,$request->_workpoint );
                // return $order;
                return $order->load([
                'products' => function($query){
                        $query->with(['prices' => function($query){
                        $query->whereIn('_type', [1,2,3,4])->orderBy('_type');
                        }
                        ,'variants'
                    ]);
                },
                'client',
                'price_list',
                'status',
                'created_by',
                'from',
                ]);
            });

            return response()->json($order);
        }catch(\Exception $e){ return response()->json(["msg" => "No se ha podido crear el pedido", "server_status" => 500, "error"=>$e]); }
    }

    public function log($case, OrderVA $order ,$create_by, $workpoint,  $_printer = null){
        $events = 0;
        switch($case){
            case 1: //Levantando pedido
                // $user = AccountVA::find($create_by);
                $order->_status = 1;
                $order->save();
                $events++;
                $log = $this->createLog($order->id, 1, [],'App\User',$create_by);
                // $user->order_log()->save($log);
            break;
            case 2: //Asignar caja
                $assign_cash_register = $this->getProcess($case,$workpoint);
                $_cash = $this->getCash($order, "Secuencial", $workpoint);
                $cashRegister = CashRegisterVA::find($_cash);
                $order->_status = 2;
                $order->save();
                $events++;
                $log = $this->createLog($order->id, 2, [],'App\CashRegister',$cashRegister->id);
                // $cashRegister->order_log()->save($log);// The system assigned cash register
            case 3: //Recepción
                if(!$_printer){
                    $printer = PrinterVA::where([['_type', 1], ['_workpoint', $workpoint]])->first();
                }else{
                    $printer = PrinterVA::find($_printer);
                }

                $_workpoint_to = $order->_workpoint_from;
                $order->refresh(['created_by', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                        });
                    }]);
                }, 'client', 'price_list', 'status', 'created_by', 'from', 'history']);
                // $cash_ = $order->history->filter(function($log){
                //     return $log->pivot->_status == 2;
                // })->values()->all()[0];
                $cash_ = $order->history->filter(function($log){
                    return $log->pivot->_status == 2;
                })->values()->first();
                $cellerPrinter = new PrinterController();
                $cellerPrinter->orderReceipt($printer->ip ,$order, $cash_); /* INVESTIGAR COMO SALTAR A LA SIGUIENTE SENTENCIA DESPUES DE X TIEMPO */
                $validate = $this->getProcess(3,$workpoint); // Verificar si la validación es necesaria
                if($validate[0]['active']){
                    $user = AccountVA::find($create_by);
                    // Order was passed next status by
                    $log = $this->createLog($order->id, 3, [],'App\User',$create_by);
                    // $user->order_log()->save($log);
                    $order->_status = 3;
                    $order->save();
                    $events++;
                    break;
                }
            case 4: //Por surtir
                $to_supply = $this->getProcess(4,$workpoint);
                if($to_supply[0]['active']){
                    $bodegueros = AccountVA::where('_wp_principal',$workpoint)
                    ->whereIn('_rol', [6,7])
                    // ->whereNotIn('_status', [4,5])
                    ->count();
                    $tickets = 100000000;
                    $in_suppling = OrderVA::where([
                        ['_workpoint_from', $workpoint],
                        ['_status', $case] // Status Surtiendo
                    ])->count(); // Para saber cuantos pedidos se estan surtiendo
                    if($in_suppling>($bodegueros*$tickets)){
                        // Poner en status 4 (el pedido esta por surtir)
                        $user = AccountVA::find($create_by);
                        // Order was passed next status by
                        $log = $this->createLog($order->id, 4, [],'App\User',$create_by);
                        $user->order_log()->save($log);
                        $order->_status = 4;
                        $order->save();
                        $events++;
                        break;
                    }
                }
            case 5: //Surtiendo
                $_workpoint_to = $order->_workpoint_from;
                $order->load([
                'created_by',
                'products' => function($query) use ($_workpoint_to){
                    $query->with([
                        'locations' => function($query)  use ($_workpoint_to){
                            $query->where('deleted_at',null)->whereHas('celler', function($query) use ($_workpoint_to){
                                $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                            });
                        },
                        'stocks' => function($query) use ($_workpoint_to){
                        $query->where('_workpoint', $_workpoint_to);
                        }
                    ]);
                },
                'client',
                'price_list',
                'status',
                'created_by',
                'from',
                'history']);
                $cash_ = $order->history->filter(function($log){
                    return $log->pivot->_status == 2;
                })->values()->all()[0];
                // $printer = null;
                // if(count($order->products) > 20){
                //     $printer = PrinterVA::where([['_type', 2], ['_workpoint', $workpoint], ['name', 'MAYOREO']])->first();
                // }else{
                //     $printer = PrinterVA::where([['_type', 2], ['_workpoint', $workpoint], ['name', 'LIKE', '%'.$cash_->pivot->responsable->num_cash.'%']])->first();
                // }

                // if(!$printer){
                //     $printer = PrinterVA::where([['_type', 2], ['_workpoint', $workpoint]])->first();
                // }


                $printerQuery = PrinterVA::where('_type', 2)
                    ->where('_workpoint', $workpoint);

                if ($order->products->sum('pivot.amount') > 20 && $order->_workpoint_from == 2) {
                    $printer = $printerQuery->where('name', 'MAYOREO')->first();
                } else {
                    $printer = $printerQuery
                        ->where('name', 'LIKE', '%' . $cash_->pivot->responsable->num_cash . '%')
                        ->first();
                }

                if (!$printer) {
                    $printer = PrinterVA::where('_type', 2)
                        ->where('_workpoint', $workpoint)
                        ->first();
                }
                $cellerPrinter = new PrinterController();//cambia el printerport por 9100
                $printed = $cellerPrinter->orderTicket2($printer->ip,$order, $cash_);
                if($printed){
                    $order->printed = $order->printed +1;
                    $order->save();
                }
                // $user = AccountVA::find($create_by);
                $log = $this->createLog($order->id, 5, [],'App\User',$create_by);
                // $user->order_log()->save($log);
                $order->_status = 5;
                $order->save();
                $events++;
                break;
        }
        $order->load('history');
          $news_logs = $order->history->filter(function($statu) use($case){
            return $statu->id >= $case;
        })->values()->map(function($event) use ($events){
            return [
                "id" => $event->id,
                "name" => $event->name,
                "active" => $event->active,
                "allow" => $event->allow,
                "details" => json_decode($event->pivot->details),
                "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                "events" => $events
            ];
        })->toArray();
        return collect($news_logs)->last();
        // return $order;
    }


    public function getProcess($_status = "all", $workpoint){

        if($_status == "all"){
            $status = OrderProcessVA::with(['config' => function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            }])->get()->map(function($status){
                return [
                    "id" => $status->id,
                    "name" => $status->name,
                    "active" => $status->config[0]->pivot->active,
                    "allow" => $status->allow,
                    "details" => json_decode($status->config[0]->pivot->details)
                ];
            });
        }else{

            $status = OrderProcessVA::with(['config' => function($query) use ($workpoint){
                $query->where('_workpoint', $workpoint);
            }])->where('id', $_status)->get()->map(function($status){
                return [
                    "id" => $status->id,
                    "name" => $status->name,
                    "active" => $status->config[0]->pivot->active,
                    "allow" => $status->allow,
                    "details" => $status->config[0]->pivot->details
                ];
            });
        }
        return $status;
    }

    public function getCash($order, $mood, $workpoint){
        if($order->_order){
            $order = OrderVA::with('history')->find($order->_order);
            $cash_ = $order->history->filter(function($log){
                return $log->pivot->_status == 2;
            })->values()->all();
            if(count($cash_)>0){
                return $cash_[0]->pivot->responsable->id;
            }
        }
        switch($mood){
            case "Secuencial":
                $date_from = new \DateTime();
                $date_from->setTime(0,0,0);
                $date_to = new \DateTime();
                $date_to->setTime(23,59,59);
                $cashRegisters = CashRegisterVA::withCount(['order_log' => function($query) use($date_from, $date_to){
                    $query->where([['created_at', '>=', $date_from], ['created_at', '<=', $date_to]]);
                }])->where([['_workpoint', $workpoint], ["_status", 1]])->get()->sortBy('num_cash');
                $inCash = array_column($cashRegisters->toArray(), 'order_log_count');
                $_cash = $cashRegisters[array_search(min($inCash), $inCash)]->id;
                return $_cash;
        }

    }

    public function orderCatalog(Request $request){
        $order = $request->order;
        $printer = $request->printer;
        $products = $order['products'];
        $array_pr = [];
        foreach ($products as $row) {
            $array_pr[$row['id']] = [
                'kit' => '',
                'amount' => $row['pivot']['amount'],
                'amountDelivered' => $row['pivot']['amountDelivered'],
                'price' => $row['pivot']['price'],
                'toDelivered' => $row['pivot']['toDelivered'],
                'total' => $row['pivot']['total'],
                'units' => $row['pivot']['units'],
                '_price_list' => $row['pivot']['_price_list'],
                '_supply_by' => $row['pivot']['_supply_by'],
            ];
        }
        $order = OrderVA::find($order['id']);
        if(count($array_pr) > 0){
            $order->products()->attach($array_pr);
        }
        $_workpoint_to = $order->_workpoint_from;

        $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
            $query->with(['locations' => function($query)  use ($_workpoint_to){
                $query->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                });
            }]);
        }, 'client', 'price_list', 'status', 'created_by', 'from', 'history']);

        $_status = $this->getNextStatus($order);
        $_printer = isset($request->_printer) ? $request->_printer : null;
        $_process = array_column(OrderProcessVA::all()->toArray(), 'id');
        if(in_array($_status, $_process)){
            $result = $this->log($_status, $order, $order->_created_by, $_workpoint_to  ,$_printer['id']);
            if($result){
                return response()->json(['success' => true, 'status' => $result, "server_status" => 200]);
            } return response()->json(['success' => false, 'status' => null, 'msg' => "No se ha podido cambiar el status", "server_status" => 500]);
        } return response()->json(['success' => false, 'msg' => "Status no válido", "server_status" => 400]);
    }

    public function getNextStatus(OrderVA $order){
        if($order->_status == 100 || $order->_status == 101){
            $previous_log = $order->history->sortByDesc(function($log){
                return $log->pivot->created_at;
            })->filter(function($log) use($order){
                return $log->id != $order->_status;
            })->values()->all()[0];
            $_status = $previous_log->id;
        }else{
            $_status = $order->_status + 1;
        }
        return $_status;
    }

    public function getOrders(Request $request){
        // return $request->all();
        $workpoint = $request->wid;
        $user = $request->uid;
        $view = $request->view;
        $units = ProductUnitVA::where('id','!=',4)->get();
        $rules = SeasonsVA::with('rules')->get();
        $printers = PrinterVA::where('_workpoint',$workpoint)->get();
        $accounts = null;
        $process= OrderProcessVA::all();
        $orders = OrderVA::withCount('products')
                    ->with(['status', 'created_by', 'from','history'])
                    ->where('_workpoint_from',$workpoint)
                    ->whereDate('created_at', now()->format('Y-m-d'));
        if($view == "sales"){
            $orders = $orders->where('_created_by',$user)->get();
        }else{
            $orders = $orders->get();
            $user = AccountVA::with(['orders' => fn($q)=> $q->whereDate('created_at',now())->where('_workpoint_from',$workpoint)])->whereHas('orders', function($q) use($workpoint) { $q->whereDate('created_at',now())->where('_workpoint_from',$workpoint);})->get();
        }
        $res = [
            "process"=>$process,
            "user"=>$accounts,
            "orders"=>$orders,
            "units"=>$units,
            "rules"=>$rules,
            "prints"=>$printers
        ];

        return response()->json($res,200);
    }

    public function nextStepCheck(Request $request){
        // $ordered = $request->id
        $createRequired = null;
        $order = OrderVA::find($request->id);
        if($order){
            if($order->_status == 3){
                $_workpoint_to = $order->_workpoint_from;
                $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                        });
                    },'stocks' =>  fn($q) => $q->where('id',1)]);
                },
                'client', 'price_list', 'status', 'created_by', 'from', 'history']);


                if($order->_workpoint_from == 4){
                    $countBoxes = $order->products->where('pivot._supply_by', 3)->sum('pivot.amount');
                    // return $countBoxes;
                    if($countBoxes <= 10 && $countBoxes > 0 ){
                        $createRequired = $this->createRequiredDirect($order);
                    }
                }


                $_status = $this->getNextStatus($order);
                $_printer = isset($request->_printer) ? $request->_printer : null;
                $_process = array_column(OrderProcessVA::all()->toArray(), 'id');
                if(in_array($_status, $_process)){
                    $result = $this->log($_status, $order, $order->_created_by, $_workpoint_to);
                    if($result){
                        return response()->json(['success' => true, 'status' => $result, "server_status" => 200, 'order'=>$order, "requisition"=>$createRequired],200);
                    } return response()->json(['success' => false, 'status' => null, 'msg' => "No se ha podido cambiar el status", "server_status" => 500],500);
                } return response()->json(['success' => false, 'msg' => "Status no válido", "server_status" => 400],400);
            }else{
                return response()->json(['success' => false, 'status' => null, 'msg' => "Aun no esta listo para validar o ya se valido ".$request->id , "server_status" => 404],404);
            }
        }else{
             return response()->json(['success' => false, 'status' => null, 'msg' => "No existe el pedido ".$request->id , "server_status" => 404],404);
        }
    }
    public function nextStepPrv(Request $request){
        $order = OrderVA::find($request->id);
        if($order){
                $_workpoint_to = $order->_workpoint_from;
                $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
                    $query->with(['locations' => function($query)  use ($_workpoint_to){
                        $query->whereHas('celler', function($query) use ($_workpoint_to){
                            $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                        });
                    },'stocks' =>  fn($q) => $q->where('id',1)]);
                },
                'client', 'price_list', 'status', 'created_by', 'from', 'history']);

                $_status = $this->getNextStatus($order);
                $_printer = isset($request->_printer) ? $request->_printer : null;
                $_process = array_column(OrderProcessVA::all()->toArray(), 'id');
                if(in_array($_status, $_process)){
                    $result = $this->log($_status, $order, $order->_created_by, $_workpoint_to, $_printer['id']);
                    if($result){
                        return response()->json(['success' => true, 'status' => $result, "server_status" => 200, 'order'=>$order],200);
                    } return response()->json(['success' => false, 'status' => null, 'msg' => "No se ha podido cambiar el status", "server_status" => 500],500);
                } return response()->json(['success' => false, 'msg' => "Status no válido", "server_status" => 400],400);
        }else{
             return response()->json(['success' => false, 'status' => null, 'msg' => "No existe el pedido ".$request->id , "server_status" => 404],404);
        }
    }

    public function createRequiredDirect(OrderVA $order){
        $toSupply = [];

        $num_ticket = Invoice::where('_workpoint_to',1)
                                    ->whereDate('created_at',now())
                                    ->count()+1;
        $num_ticket_store = Invoice::where('_workpoint_from', $order->_workpoint_from)
                                        ->whereDate('created_at', now())
                                        ->count()+1;
        $requisition = new Invoice;
        $requisition->notes =" Pedido preventa #".$order->id.", ".$order->name;
        $requisition->num_ticket = $num_ticket;
        $requisition->num_ticket_store = $num_ticket_store;
        $requisition->_created_by = 1;
        $requisition->_workpoint_from = $order->_workpoint_from;
        $requisition->_workpoint_to = 1;
        $requisition->_type = 5;
        $requisition->printed = 0;
        $requisition->_warehouse = 'GEN';
        $requisition->time_life = "00:15:00";
        $requisition->_status = 4;
        $requisition->save();
        $res = $requisition->fresh();
        $log = $this->logInt($res->id,$res->_status);
        if($log){
            $toSupply = [];
            $products = $order->products->where('pivot._supply_by', 3);
            foreach($products as $product){
                    $required = $product['pivot']['amount'];
                        $toSupply[$product->id] = [
                            'units' =>$product['pivot']['units'] ,
                            "cost" => $product->cost,
                            'amount' => $required,
                            "_supply_by" => 3,
                            'comments' => '',
                            "stock" => count($product->stocks) > 0 ? $product->stocks[0]->pivot->stock : 0,
                            // "toDelivered" => $required,
                            // "checkout" => 1,
                            // "ipack" => $product->pieces
                        ];

            }
            if(isset($toSupply)){ $requisition->products()->attach($toSupply);}

            $npartition = new partitionRequisition([
                '_requisition' => $requisition->id,
                '_status' => 4,
                // 'entry_key' => md5($requisition->id),
                '_warehouse' => $requisition->_warehouse
            ]);
            $npartition->save();

            foreach ($requisition->products as $prod) {
                $requisition->products()->updateExistingPivot($prod->id, ['_partition' => $npartition->id]);
            }
            $reqio = $npartition->load([
                'status',
                'log',
                'products.locations' => fn($q) => $q->whereHas('celler', fn($l) => $l->where('_workpoint', 1))->whereNull('deleted_at'),
                'requisition.type',
                'requisition.status',
                'requisition.to',
                'requisition.from',
                'requisition.created_by',
                'requisition.log'
            ]);
            $miniprinter   = new PrinterController();

            $printers = PrinterVA::where([['_workpoint', $order->_workpoint_from],['_type', 2]])->whereIn('name',['RECIBOS','MAYOREO'])->get();
            foreach($printers as $printer){
                 $printedProvider = $miniprinter->PartitionDirect($printer->ip, $reqio, $order);
            }
            // $printedProvider = $miniprinter->PartitionDirect($printerQuery->ip, $reqio, $order);

            $ipProvider    = env("PRINTER_DIRECT");
            // $printedProvider = $miniprinter->PartitionDirect($ipProvider, $reqio, $order);
            $printedProvider = $miniprinter->PartitionDirect($ipProvider, $reqio, $order);

            if ($printedProvider) {
                $requisition->increment('printed');
            } else {
                // $this->sendWhatsapp("120363185463796253@g.us",
                //     "El pedido " . $requisition->id . " no se logró imprimir, favor de revisarlo (ES DIRECTO (SP2))"
                // );
            }
            $log = $requisition->log
                ->where('id', '>=', 4)
                ->map(function ($event) {
                    return [
                        "id"         => $event->id,
                        "name"       => $event->name,
                        "active"     => $event->active,
                        "allow"      => $event->allow,
                        "details"    => json_decode($event->pivot->details),
                        "created_at" => $event->pivot->created_at->format('Y-m-d H:i'),
                        "updated_at" => $event->pivot->updated_at->format('Y-m-d H:i')
                    ];
                })
                ->values();
            return [
                "requisition" => $requisition->load(['type', 'status', 'to', 'from', 'created_by', 'log','products']),
                "partition"=>$reqio,
                "log" => $log
            ];
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

    public function getSettings($sid){
        $config = OrderProcessVA::with([
            'config' => fn($q) => $q->where('_workpoint', $sid)
        ])->where('allow',1)->get();
        $cash = CashRegisterVA::with(['status'])->where('_workpoint',$sid)->get();

        $res = [
            "config"=>$config,
            "cash"=>$cash
        ];
        return response()->json($res,200);
    }

    public function changeStatusCash(Request $request){
        $cash = CashRegisterVA::find($request->id);
        if($cash){
            $cash->_status = $request->_status;
            $cash->save();
            $res = $cash->load(['status']);
            return response()->json($res,200);
        }else{
            return response()->json(['msg'=>'No se encuentra la caja'],404);
        }
    }

    public function changeConfig(Request $request){
        $config = OrderProcessConfigVA::where([['_workpoint',$request->_workpoint],['_process',$request->_process]])->update(['active'=>$request->active]);
        if($config){
            // $config->active = $request->active;
            // $config->save();
            return response()->json($config,200);
        }else{
            return response()->json(['msg'=>'No se encuentra el proceso'],404);
        }
    }

    public function reimpresionClientTicket(Request $request){
        $order = OrderVA::with((['created_by', 'products', 'client', 'price_list', 'status', 'created_by', 'from', 'history']))->find($request->_order);
        if($order->_status>2){
            $printer = PrinterVA::find($request->_printer);
            $miniprinter = new PrinterController($printer->ip, 9100, 5);
            $cash_ = $order->history->filter(function($log){
                return $log->pivot->_status == 2;
            })->values()->all()[0];
            $res = $miniprinter->orderReceipt($printer->ip ,$order, $cash_);
            return response()->json(["success" => $res, "msg" => "ok", "server_status" => 200]);
        }else{
            return response()->json(["success" => false, "msg" => "Aun no se puede imprimir el ticket", "server_status" => 500]);
        }
        return response()->json(["success" => false, "msg" => "Folio no encontrado", "server_status" => 200]);
    }

    public function reimpresion(Request $request){
        $order = OrderVA::find($request->_order);
        $_workpoint_to = $order->_workpoint_from;
        $order->load(['created_by', 'products' => function($query) use ($_workpoint_to){
            $query->with([
                'locations' => function($query)  use ($_workpoint_to){
                $query->where('deleted_at',null)->whereHas('celler', function($query) use ($_workpoint_to){
                    $query->where([['_workpoint', $_workpoint_to], ['_type', 1]]);
                });
            },
            'stocks' => function($query) use ($_workpoint_to){
                $query->where('_workpoint', $_workpoint_to);
                }
            ]);
        }, 'client', 'price_list', 'status', 'created_by', 'from', 'history']);

        $cash_ = $order->history->filter(function($log){
            return $log->pivot->_status == 2;
        })->values()->all()[0];

        $in_coming = $order->history->filter(function($log){
            return $log->pivot->_status == 5;
        })->values()->all()[0];
        $printer = PrinterVA::find($request->_printer);
        $cellerPrinter = new PrinterController();
        $res = $cellerPrinter->orderTicket2($printer->ip, $order, $cash_);
        if($res){
            $order->printed = $order->printed +1;
            $order->save();
        }
        return response()->json(["success" => $res, "server_status" => 200]);
    }
}
