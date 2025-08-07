<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\CashPrinter;
use App\Models\CashLog;
use App\Models\Stores;
use App\Models\Position;
use App\Models\CashRegister;
use App\Models\CashCashier;
use App\Models\User;
use App\Models\ProviderWithdrawal;
use App\Models\Withdrawal;
use App\Models\Sale;
use App\Models\SaleBodie;
use App\Models\SalePayment;
use App\Models\PaymentMethod;
use App\Models\IngressClient;
use App\Models\Ingress;
use App\Models\SeasonsVA;
use App\Models\Advances;
use App\Models\AccountVA;
use App\Models\OrderVA;
use App\Models\OrderLogVA;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;



class CashController extends Controller
{
    public function index(Request $request){
        //
        $res = [
            "cashes"=>[],
            "cashiers"=>[],
            "printers"=>[]
        ];
        $sid = $request->sid;
        $uid = $request->uid;
        $user = USER::find($uid);
        if($user->_rol == 3 ||  $user->_rol == 13 ){//cajero
           $res['cashes'] = CashRegister::with([
                'store',
                'status',
                'cashier' => fn($q) =>  $q->with('user.staff','print')->whereDate('open_date', now()->format('Y-m-d')),
            ])
            ->where('_store',$sid)
            ->where('_status',1)
            ->whereHas('cashier', function($q) use ($user) {
                 $q->where('_user',$user->id)->whereDate('open_date', now()->format('Y-m-d'));
            })
            ->get();
            return response()->json($res);
        }else if(in_array($user->_rol, [1, 2, 5, 6, 12])){
            $cash = CashRegister::with([
                'store',
                'status',
                'cashier' => fn($q) =>  $q->with('user.staff','print')->max('open_date'),
            ])
            ->where('_store',$sid)
            ->get();
            $res = [
                "cashes"=>$cash,
                "cashiers"=>User::with(['staff'])->where([['_rol',3],['_store',$sid]])->get(),
                // "cashiers"=>User::with(['staff'])->where([['_rol',13],['_store',$sid]])->get(),

                "printers"=>CashPrinter::where('_store',$sid)->get(),
            ];
            return response()->json($res,200);
        }

    }

    public function openCash(Request $request){
        $cashier = $request->cashier;
        $uid = $request->uid;
        $nwcashier = new CashCashier;
        $nwcashier->_cash = $cashier['id'];
        $nwcashier->_user = $cashier['cashier']['user']['id'];
        $nwcashier->_tck_print = $cashier['cashier']['print']['id'];
        $nwcashier->cash_start = $cashier['cashier']['cash_start'];
        $nwcashier->open_date = now();
        $nwcashier->close_date = now();
        $nwcashier->save();
        $res = $nwcashier->load(['user.staff','print']);
        if($res){
            $nwLog = new CashLog;
            $nwLog->_type = 1;
            $nwLog->_user = $uid;
            $nwLog->_cash = $res->_cash;
            $nwLog->details = json_encode($res);
            $nwLog->save();
            $rlo = $nwLog;
            if($rlo){
                $updState = CashRegister::where('id',$cashier['id'])->first();
                $updState->_status = 1;
                $updState->save();
                $response = $updState->load([
                    'store',
                    'status',
                    'cashier' => fn($q) =>  $q->with('user.staff','print')->max('open_date'),
                ]);
                return response()->json($response);
            }else{
                return response()->json('No se logro insertar el log',500);
            }
        }else{
            return response()->json('No se logro realizar la apertura',500);
        }
    }

    public function getCash(Request $request){
        $uid = $request->uid;
        $cash = $request->cash;
        $sid = $request->sid;
        $user = User::find($uid);
        $methods = PaymentMethod::where('id','!=',5)->get();
        $providers = ProviderWithdrawal::all();
        $clientIngress = IngressClient::all();
        $rules = SeasonsVA::with('rules')->get();

        $query = CashRegister::with([
            'store',
            'status',
            'cashier' => fn($q) =>  $q->with('user.staff','print')->whereDate('open_date', now()->format('Y-m-d')),
        ])->where([['_status',1],['id',$cash],['_store',$sid]]);

        if($user->_rol == 3){
           $res  = $query->whereHas('cashier', function($q) use ($uid) {
                    $q->where('_user',$uid);
            })->first();
        }else if(in_array($user->_rol, [1, 2, 5, 6])){
           $res =  $query->first();
        }
        if($res && $res->cashier){
            $response = [
                "rules"=>$rules,
                "cash"=>$res,
                "methods"=>$methods,
                "providers"=>$providers,
                "clientIngress"=>$clientIngress
            ];
            return response()->json($response);
        }else{
            return response()->json('No puedes ingresar a la caja',401);
        }
    }

    public function getPaidMethods(){
        $res = PaymentMethod::all();
        return response()->json($res,200);
    }

    public function addSale(Request $request){
        $sale = $request->order;
        $cash = $request->cashier;
        DB::transaction(function() use ($cash, &$nwSale, $sale) {
            $payments = $sale['payments'];
            $nextDocumentId = Sale::where('_cash', $cash['id'])->max('document_id') + 1;
            $staff = Staff::where('id_va',$sale['created_by']['id'])->first();
            $nwSale = new Sale;
            $nwSale->_client = $sale['_client'];
            $nwSale->client_name = $sale['client']['name'];
            $nwSale->_staff = $staff->id;
            $nwSale->_cashier = $cash['cashier']['id'];
            $nwSale->_order = $sale['id'];
            $nwSale->_state = 1;
            $nwSale->total = $sale['total'];
            $nwSale->change = $sale['change'];
            $nwSale->_pfpa = $payments['PFPA']['id']['id'];
            $nwSale->pfpa_import =$payments['PFPA']['val'];
            $nwSale->_sfpa = isset($payments['SFPA']['id']) ? $payments['SFPA']['id']['id'] : null;
            $nwSale->sfpa_import = isset($payments['SFPA']['id']) ? $payments['SFPA']['val'] : null;
            $nwSale->_val = isset($payments['VALE']['id']) ? $payments['VALE']['id']['id'] : null;
            $nwSale->val_import = isset($payments['VALE']['id']) ? $payments['VALE']['val'] : null;
            $nwSale->val_code = isset($payments['VALE']['id']) ? $payments['VALE']['id']['code']  : null;
            $nwSale->_cash = $cash['id'];
            $nwSale->_store = $cash['_store'];
            $nwSale->document_id = $nextDocumentId;
            $nwSale->save();
            $res = $nwSale->fresh();
            if($res){
                if($payments['conditions']['super']){
                    $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $res->total;
                    if($payments['conditions']['createWithdrawal']){
                        $envio = [
                            "cash"=>$cash,
                            "withdrawal"=>[
                                "concept"=>"Devolucion Sobrante pagos ticket ".$res->id,
                                "import"=>$cambio,
                                "providers"=>[
                                    "val"=>["id"=>833]
                                ]
                            ]
                        ];
                        $addWith = $this->addWithrawalSobrante($envio);

                    }else{
                        $envio = [
                            "sale"=>$res->id,
                            "cash"=>$cash,
                            "advance"=>[
                                "client"=>[
                                    "id"=>$res->_client,
                                    "name"=>$res->client_name
                                ],
                            "import"=>$cambio,
                            "observacion"=>"Vale Sobrante de la venta ".$res->id,
                            ]
                        ];
                        $addAdvance = $this->addAdvancesSobrante($envio);
                    }
                }
                $products = $sale['products'];
                $insArt = array_map(function($val)use($res){
                    return [
                        "_sale"=>$res->id,
                        "code"=>$val['code'],
                        "description"=>$val['description'],
                        "cost"=>$val['cost'],
                        "amount"=>$val['pivot']['toDelivered'],
                        "price"=>$val['pivot']['price'],
                        "_rate"=>$val['pivot']['_price_list'],
                        "total"=>$val['pivot']['total']
                    ];
                },$products);
                $nrwBodi =  SaleBodie::insert($insArt);
                $filtered = array_filter($payments, function($val) {
                    return isset($val['id']) && !is_null($val['id']) && $val['val'] > 0;
                });
                $change =floatval($res->change);
                $changeVale = ["total"=>0,"state"=>false];
                if ($change > 0) {
                    foreach ($filtered as $key => &$payment) {
                        if (isset($payment['id']['alias']) && $payment['id']['alias'] === 'EFE') {
                            $original = floatval($payment['val']);
                            $adjusted = $original - $change;
                            $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                            break;
                        }
                    }
                }else if ( isset($payments['conditions']['super']) && $payments['conditions']['super']){
                    $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $res->total;
                    if($cambio > 0){
                        foreach ($filtered as $key => &$payment) {
                            if (isset($payment['id']['id']) && $payment['id']['id'] === 5 ) {

                                $original = floatval($payment['val']);
                                $adjusted = $original - $cambio;
                                $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                                $changeVale = ['total'=>$adjusted,'state'=>true];
                                break;
                            }
                        }
                    }
                }
                if($res->val_code){
                    $upd = Advances::where('fs_id',$res->val_code)->first();
                    if($upd &&  $changeVale['state']){
                        $upd->_status = 1;
                        $upd->_sale_aplication = $res->id;
                        $upd->import =  $changeVale['total'];
                        $upd->save();
                    }else{
                        $upd->_status = 1;
                        $upd->_sale_aplication = $res->id;
                        $upd->save();
                    }
                }
                $insPay = array_map(function($val) use ($res){
                    return [
                        "_sale"=>$res->id,
                        '_payment' => $val['id']['id'],
                        'import' => $val['val']
                    ];
                }, $filtered);
                $nrwPaym =  SalePayment::insert($insPay);
                if($nrwPaym && $nrwBodi){
                    $res->load(['bodie','cashier.cash.tpv','cashier.print','cashier.user.staff','cashier.cash.store','payments','staff']);
                    $cellerPrinter = new PrinterController();
                    $printed = $cellerPrinter->printck($res,$payments);
                }
            return response()->json($filtered);
            }
        });
    }

    public function addSaleStandar(Request $request){
        $sale = $request->order;
        $cash = $request->cashier;
        $res = null;
        DB::transaction(function() use ($cash, &$nwSale, $sale, &$res) {
            $payments = $sale['payments'];
            $nextDocumentId = Sale::where('_cash', $cash['id'])->max('document_id') + 1;
            $staff = Staff::find($sale['dependiente']['id']);
            $nwSale = new Sale;
            $nwSale->_client = $sale['client']['id'];
            $nwSale->client_name = $sale['client']['name'];
            $nwSale->_staff = $staff->id;
            $nwSale->_cashier = $cash['cashier']['id'];
            $nwSale->_state = 1;
            $nwSale->iva = isset($sale['iva']) ? $sale['iva'] : null;;
            $nwSale->subtotal = isset($sale['subtotal']) ? $sale['subtotal'] : null;;
            $nwSale->total = $sale['total'];
            $nwSale->change = $sale['change'];
            $nwSale->_pfpa = $payments['PFPA']['id']['id'];
            $nwSale->pfpa_import =$payments['PFPA']['val'];
            $nwSale->_sfpa = isset($payments['SFPA']['id']) ? $payments['SFPA']['id']['id'] : null;
            $nwSale->sfpa_import = isset($payments['SFPA']['id']) ? $payments['SFPA']['val'] : null;
            $nwSale->_val = isset($payments['VALE']['id']) ? $payments['VALE']['id']['id'] : null;
            $nwSale->val_import = isset($payments['VALE']['id']) ? $payments['VALE']['val'] : null;
            $nwSale->val_code = isset($payments['VALE']['id']) ? $payments['VALE']['id']['code']  : null;
            $nwSale->_cash = $cash['id'];
            $nwSale->_store = $cash['_store'];
            $nwSale->document_id = $nextDocumentId;
            $nwSale->save();
            $res = $nwSale->fresh();
            if($res){
                if($payments['conditions']['super']){
                    $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $res->total;
                    if($payments['conditions']['createWithdrawal']){
                        $envio = [
                            "cash"=>$cash,
                            "withdrawal"=>[
                                "concept"=>"Devolucion Sobrante pagos ticket ".$res->id,
                                "import"=>$cambio,
                                "providers"=>[
                                    "val"=>["id"=>833]
                                ]
                            ]
                        ];
                        $addWith = $this->addWithrawalSobrante($envio);
                    }else{
                        $envio = [
                            "sale"=>$res->id,
                            "cash"=>$cash,
                            "advance"=>[
                                "client"=>[
                                    "id"=>$res->_client,
                                    "name"=>$res->client_name
                                ],
                            "import"=>$cambio,
                            "observacion"=>"Vale Sobrante de la venta ".$res->id,
                            ]
                        ];
                        $addAdvance = $this->addAdvancesSobrante($envio);
                    }
                }
                $products = $sale['products'];
                $insArt = array_map(function($val)use($res){
                    return [
                        "_sale"=>$res->id,
                        "code"=>$val['code'],
                        "description"=>$val['description'],
                        "cost"=>$val['cost'],
                        "amount"=>$val['pivot']['toDelivered'],
                        "price"=>$val['pivot']['price'],
                        "_rate"=>$val['pivot']['_price_list'],
                        "iva"=>isset($val['pivot']['iva']) ?$val['pivot']['iva'] : null,
                        "subtotal"=>isset($val['pivot']['subtotal']) ?$val['pivot']['subtotal'] : null,
                        "total"=>$val['pivot']['total']
                    ];
                },$products);
                $nrwBodi =  SaleBodie::insert($insArt);
                $change = floatval($res->change);
                $filtered = array_filter($payments, function($val) {
                    return isset($val['id']) && !is_null($val['id']) && $val['val'] > 0;
                });
                $changeVale = ["total"=>0,"state"=>false];
                if ($change > 0) {
                    foreach ($filtered as $key => &$payment) {
                        if (isset($payment['id']['alias']) && $payment['id']['alias'] === 'EFE') {
                            $original = floatval($payment['val']);
                            $adjusted = $original - $change;
                            $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                            break;
                        }
                    }
                }else if ( isset($payments['conditions']['super']) && $payments['conditions']['super']){
                    $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $res->total;
                    if($cambio > 0){
                        foreach ($filtered as $key => &$payment) {
                            if (isset($payment['id']['id']) && $payment['id']['id'] === 5 ) {

                                $original = floatval($payment['val']);
                                $adjusted = $original - $cambio;
                                $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                                $changeVale = ['total'=>$adjusted,'state'=>true];
                                break;
                            }
                        }
                    }
                }
                if($res->val_code){
                    $upd = Advances::where('fs_id',$res->val_code)->first();
                    if($upd &&  $changeVale['state']){
                        $upd->_status = 1;
                        $upd->_sale_aplication = $res->id;
                        $upd->import =  $changeVale['total'];
                        $upd->save();
                    }else{
                        $upd->_status = 1;
                        $upd->_sale_aplication = $res->id;
                        $upd->save();
                    }
                }
                $insPay = array_map(function($val) use ($res){
                    return [
                        "_sale"=>$res->id,
                        '_payment' => $val['id']['id'],
                        'import' => $val['val']
                    ];
                }, $filtered);
                $nrwPaym =  SalePayment::insert($insPay);
                if($nrwPaym && $nrwBodi){
                    $res->load(['bodie','cashier.cash.tpv','cashier.print','cashier.user.staff','cashier.cash.store','payments','staff']);
                    $cellerPrinter = new PrinterController();
                    $printed = $cellerPrinter->printck($res,$payments);
                }
            }
        });
        if ($res) {
            return response()->json($res, 200);
        } else {
            return response()->json(['error' => 'No se pudo guardar la venta'], 500);
        }
    }

    public function addWithrawalSobrante($envio){

        $addWith = http::post($envio['cash']['store']['ip_address'].'/storetools/public/api/Cashier/addWithdrawal',$envio);
        // $addWith = http::post('192.168.10.160:1619'.'/storetools/public/api/Cashier/addWithdrawal',$envio);
        if($addWith->status() == 200){
            $newWith = new Withdrawal;
            $newWith->fs_id = $addWith['folio'];
            $newWith->concept = $envio['withdrawal']['concept'];
            $newWith->_providers = $envio['withdrawal']['providers']['val']['id'];
            $newWith->import = $envio['withdrawal']['import'];
            $newWith->_cashier = $envio['cash']['cashier']['id'];
            $newWith->save();
            $withdr = $newWith->load(['provider','cashier.cash.tpv']);
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printret($withdr);
            // return response()->json(["printed"=>$printed,"retirada"=>$res],200);
        }
    }

    public function addAdvancesSobrante($envio){
        $addAdvance = http::post($envio['cash']['store']['ip_address'].'/storetools/public/api/Cashier/addAdvance',$envio);
        // $addAdvance = http::post('192.168.10.160:1619'.'/storetools/public/api/Cashier/addAdvance',$envio);
        if($addAdvance->status() == 200){
            $newAdvances = new Advances;
            $newAdvances->fs_id = $addAdvance['folio'];
            $newAdvances->_client = $envio['advance']['client']['id'];
            $newAdvances->client_name = $envio['advance']['client']['name'];
            $newAdvances->import = $envio['advance']['import'];
            $newAdvances->observations = $envio['advance']['observacion'];
            $newAdvances->sale_origin = $envio['sale'];
            $newAdvances->_status = 0;
            $newAdvances->_type = 1;
            $newAdvances->_cashier = $envio['cash']['cashier']['id'];
            $newAdvances->save();
            $addvance = $newAdvances->load(['cashier.cash.tpv']);
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printAdvance($addvance);
        }
    }

    public function getDependiente(Request $request){
        $val = $request->val;
        $store = $request->sto;
        $buscar = Staff::where('_store',$store)
            ->where('complete_name', 'like', '%' . $val . '%')
            ->orWhere('id_tpv', $val)
            ->orWhere('id', $val)
            ->get();
        return response()->json($buscar,200);
    }

    public function closeCash(Request $request){
        $bullet = $request->close;
        $caja = $request->cash;
        $declarec = $request->total;
        $uid = $request->uid;

        $cashier = CashCashier::find($caja['cashier']['id']);
        if($cashier){
            $cashier->cash_close = $declarec;
            $cashier->details = json_encode($bullet);
            $cashier->close_date = now();
            $res = $cashier->save();

            if($res){
                $totalesPorTipo = SalePayment::whereHas('sale', function ($q) use ($caja) {
                    $q->where('_cashier', $caja['cashier']['id']);
                })
                ->select('_payment', DB::raw('SUM(import) as total'))
                ->groupBy('_payment')
                ->with('payment')
                ->get();

                $totalEfe = $totalesPorTipo->firstWhere('_payment', 2)?->total ?? 0;
                $withdrawals = Withdrawal::where('_cashier',$caja['cashier']['id'])->sum('import');
                $ingress = Ingress::where('_cashier',$caja['cashier']['id'])->sum('import');

                // $cashier  = CashCashier::find($caja['cashier']['id']);
                $start = $cashier->cash_start;
                $efectivo = (floatval($totalEfe)  +   floatval($start) + floatval($ingress) ) - floatval($withdrawals);
                $descuadre = $declarec - $efectivo;
                $insDetails = [
                    "retiradas"=>$withdrawals,
                    "ingresos"=>$ingress,
                    "fpa"=>$totalesPorTipo,
                    "declarado"=>[
                        "modedas"=>$bullet['Monedas'],
                        "billetes"=>$bullet['Billetes']
                    ],
                    "totalDeclarado"=>$declarec,
                    "descuadre"=>$descuadre,
                    "efectivoencaja"=>$efectivo,
                ];
                $nwLog = new CashLog;
                $nwLog->_type = 2;
                $nwLog->_user = $uid;
                $nwLog->_cash = $caja['id'];
                $nwLog->details = json_encode($insDetails);
                $nwLog->save();
                $rlo = $nwLog;
                if($rlo){
                    $updState = CashRegister::where('id',$cashier['_cash'])->first();
                    $updState->_status = 2;
                    $updState->save();
                    $response = $updState->load([
                        'store',
                        'status',
                        'cashier' => fn($q) => $q
                            ->with('user.staff', 'print', 'withdrawal','sale','ingress','addvances')
                            ->where('id', $caja['cashier']['id']),
                    ]);
                    // return $insdetails;
                    $cellerPrinter = new PrinterController();
                    $printed = $cellerPrinter->printCut($insDetails,$response);
                    $printed = $cellerPrinter->printCut($insDetails,$response);
                    return response()->json($response);
                }else{
                    return response()->json('No se logro insertar el log',500);
                }
            }
        }
    }

    public function addWitrawal(Request $request){
        $cash = $request->cash;
        $with = $request->withdrawal;
        $addWith = http::post($cash['store']['ip_address'].'/storetools/public/api/Cashier/addWithdrawal',$request->all());
        // $addWith = http::post('192.168.10.160:1619'.'/storetools/public/api/Cashier/addWithdrawal',$request->all());
        if($addWith->status() == 200){
            $newWith = new Withdrawal;
            $newWith->fs_id = $addWith['folio'];
            $newWith->concept = $with['concept'];
            $newWith->_providers = $with['providers']['val']['id'];
            $newWith->import = $with['import'];
            $newWith->_cashier = $cash['cashier']['id'];
            $newWith->save();
            $res = $newWith->load(['provider','cashier.cash.tpv']);
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printret($res);
            return response()->json(["printed"=>$printed,"retirada"=>$res],200);
        }else{
            return response()->json('No se logro insertar la retirada',500);
        }
    }

    public function getWithdrawals(Request $request){
        $cash = $request->cash;
        $withdrawals = Withdrawal::with('provider')->where('_cashier',$cash['cashier']['id'])->get();
            return response()->json($withdrawals,200);
    }

    public function reprintWithdrawal(Request $request){
        $cash = $request->all();
        $withdrawals = Withdrawal::with(['provider','cashier.cash.tpv'])->where('id',$cash['id'])->first();
        $cellerPrinter = new PrinterController();
        $printed = $cellerPrinter->printret($withdrawals);
        return response()->json(["printed"=>$printed,"retirada"=>$withdrawals],200);
    }

    public function reprintSale(Request $request){
        $type = $request->type;
        $cash = $request->cash;
        $id = isset($request->val) ? $request->val : null;
        if($type == 1){
            $sale = Sale::with(['payments.payment', 'bodie', 'cashier.cash.tpv', 'cashier.print', 'cashier.user.staff', 'cashier.cash.store', 'staff','pfpa','sfpa','val'])->find($id);
            if (!$sale) {
                return response()->json(['error' => 'Sale not found'], 404);
            }
        }else if($type == 2){
            $sale = Sale::with(['payments.payment', 'bodie', 'cashier.cash.tpv', 'cashier.print', 'cashier.user.staff', 'cashier.cash.store', 'staff','pfpa','sfpa','val'])->where('_cashier',$cash['cashier']['id'])->orderBy('id', 'desc')->first();
        }
        $formattedPayments = [
            "PFPA"=>[
                "id" =>  $sale->pfpa,
                "val" => $sale->pfpa_import
            ],
            "SFPA"=>[
                "id" =>  $sale->sfpa,
                "val" => $sale->sfpa_import
            ],
            "VALE"=>[
                "id" =>  $sale->val,
                "val" => $sale->val_import
            ],

        ];
        $printer = new PrinterController();
        $printer->printck($sale, $formattedPayments);

        return response()->json(['message' => 'Reimpresión realizada con éxito']);
    }

    public function addIngress(Request $request){
        $cash = $request->cash;
        $with = $request->ingress;
        $addWith = http::post($cash['store']['ip_address'].'/storetools/public/api/Cashier/addWithdrawal',$request->all());
        // $addWith = http::post('192.168.10.160:1619'.'/storetools/public/api/Cashier/addIngress',$request->all());
        if($addWith->status() == 200){
            $newWith = new Ingress;
            $newWith->fs_id = $addWith['folio'];
            $newWith->concept = $with['concept'];
            $newWith->_client = $with['client']['val']['id'];
            $newWith->import = $with['import'];
            $newWith->_cashier = $cash['cashier']['id'];
            $newWith->save();
            $res = $newWith->load(['client','cashier.cash.tpv']);
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printing($res);
            return response()->json(["printed"=>$printed,"ingreso"=>$res],200);
        }else{
            return response()->json('No se logro insertar la retirada',500);
        }
    }

    public function getIngress(Request $request){
        $cash = $request->cash;
        $withdrawals = Ingress::with('client')->where('_cashier',$cash['cashier']['id'])->get();
            return response()->json($withdrawals,200);
    }

    public function reprintIngress(Request $request){
        $cash = $request->all();
        $withdrawals = Ingress::with(['client','cashier.cash.tpv'])->where('id',$cash['id'])->first();
        $cellerPrinter = new PrinterController();
        $printed = $cellerPrinter->printing($withdrawals);
        return response()->json(["printed"=>$printed,"ingreso"=>$withdrawals],200);
    }

    public function addAdvances(Request $request){
        $cash = $request->cash;
        $advance = $request->advance;
        $addAdvance = http::post($cash['store']['ip_address'].'/storetools/public/api/Cashier/addAdvance',$request->all());
        // $addAdvance = http::post('192.168.10.160:1619'.'/storetools/public/api/Cashier/addAdvance',$request->all());

        if($addAdvance->status() == 200){
            $newAdvances = new Advances;
            $newAdvances->fs_id = $addAdvance['folio'];
            $newAdvances->_client = $advance['client']['id'];
            $newAdvances->client_name = $advance['client']['name'];
            $newAdvances->import = $advance['import'];
            $newAdvances->observations = $advance['observacion'];
            $newAdvances->_status = 0;
            $newAdvances->_type = 1;
            $newAdvances->_cashier = $cash['cashier']['id'];
            $newAdvances->save();
            $res = $newAdvances->fresh();
            return response()->json($res,200);
        }else{
            return response()->json($addAdvance->json(),$addAdvance->status());
        }
    }

    public function getSales(Request $request){
        $cash = $request->cash;
        $sales = Sale::with([
            'bodie',
            'cashier.cash.tpv',
            'cashier.print',
            'cashier.user.staff',
            'cashier.cash.store',
            'payments',
            'staff'])
        ->where('_cashier',$cash['cashier']['id'])
        ->get();
        return response($sales,200);
    }

    public function RepliedSales(){
        $res = [
            "goals"=>[],
            "fails"=>[]
        ];
        $sales = Stores::with([
            'sale.bodie',
            'sale.cashier.cash.tpv',
            'sale.cashier.user.staff',
            'sale.cashier.cash.store',
            'sale.payments.payment',
            'sale.staff',
            'sale.pfpa',
            'sale.sfpa',
            'sale.val',
            'sale' => function($q) {$q->where('_state', 1);}
        ])
        ->whereHas('sale', function($q) {$q->where('_state',1);})
        ->get();
        foreach($sales as $sale){
            $replySales = http::post($sale['ip_address'].'/storetools/public/api/Cashier/repliedSales',$sale['sale']);
            // $replySales = http::post('192.168.10.160:1619'.'/storetools/public/api/Cashier/repliedSales',$sale['sale']);
            if($replySales->status() == 200){
                foreach($replySales['goals'] as $sold){
                    $updSale = Sale::find($sold['sale']);
                    $updSale->fs_id = $sold['fs_id'];
                    $updSale->_state = 2;
                    $updSale->save();
                }
                $res[]['goals'] = ["sucursal"=>$sale['alias'],"sales"=>count($replySales['goals'])];
            }else{
                $res[]['fails'] = ["sucursal"=>$sale['alias'],"sales"=>0];
            }

        }
        return $res;
    }

    public function getOrderCash(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status > 1 && $order->_status < 10 ){
                $order->_status = 9;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $res->staff= $res->getStaff();
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


}
