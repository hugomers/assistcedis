<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ProductVA;
use App\Models\ProvidersVA;
use App\Models\ProductStockVA;
use App\Models\MakersVA;
use App\Models\ProductCategoriesVA;
use App\Models\ProductUnitVA;
use App\Models\Stores;
use App\Models\WorkpointVA;
use App\Models\ControlFigures;
use App\Models\historyPricesVA;



class ProductsController extends Controller
{
    public function getProduct($id){
        $product = ProductVA::with([
            'stocks'=> fn($q)=> $q->whereNotIn('_workpoint',[12,14,15,22,21]),
            'category.familia.seccion',
            'prices'
            // 'purchases',
            // 'sales'
        ])
        ->find($id);
        $product->total_stock = $product->stocks->sum(fn($item) => $item->pivot->stock + $item->pivot->in_transit);
        $product->details = $product->combinedAmountByYear();
        return $product;
    }

    public function index(){
        $res = [
        "categories" => ProductCategoriesVA::whereNotNull('alias')->get(),
        "providers" => ProvidersVA::all(),
        "makers" => MakersVA::all(),
        "units" => ProductUnitVA::all(),
        "medPerson"=>ProductVA::whereNotNull('large')->where([['large','!=',''],['_status','!=',4]])->select('large')->distinct()->get()
        ];
        return $res;
    }

    public function translateWarehouses(Request $request){
        $stor =[
            'fails'=>[],
            'goals'=>[]
        ];
        $pasillo = $request->pasillo;
        $wareor = $request->origen;
        $waredes = $request->destino;
        $orders = $request->orders;
        $worids = DB::connection('vizapi')->table('orders AS O')->join('workpoints AS W','W.id','O._workpoint_from')->whereIn('O.id',$orders)->select('W.*')->first();
        $workpoint = $worids->name;
        $domain = $worids->dominio;
        $notas = "Traspaso de las comandas ".implode("-",$orders)." locacion N-".$pasillo;
        $prord = DB::connection('vizapi')->table('product_ordered AS PO')->join('products AS P','P.id','PO._product')->whereIn('PO._order',$orders)->select('P.code AS ARTLTR','PO.amount AS CANLTR')->get();
        $import = [
            "AORTRA"=>$wareor,
            "ADETRA"=>$waredes,
            "COMTRA"=>$notas,
            "products"=>$prord
        ];

        $url = $domain."/storetools/public/api/Products/translate";//se optiene el inicio del dominio de la sucursal
        // $url = "192.168.10.61:1619"."/storetools/public/api/Products/translate";//se optiene el inicio del dominio de la sucursal
        $ch = curl_init($url);//inicio de curl
        $data = json_encode(["data" => $import]);//se codifica el arreglo de los proveedores
        //inicio de opciones de curl
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        //fin de opciones e curl
        $exec = curl_exec($ch);//se executa el curl
        $exc = json_decode($exec);//se decodifican los datos decodificados
        if(is_null($exc)){//si me regresa un null
            $stor['fails'] =["sucursal"=>$workpoint, "mssg"=>$exec];
        }else{
            // $stor['goals'][] = $store->alias." cambios hechos";//de lo contrario se almacenan en sucursales
            $stor['goals'] =["sucursal"=>$workpoint, "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas
        }
        curl_close($ch);//cirre de curl

        $res = [
            'IMPORTADO'=>$import,
            'SUCURSAL'=>$stor
        ];

        return response()->json($res);
    }

    public function transferStores(Request $request){
        $order = $request->id;
        $destino = $request->destino;
        $wordes = DB::connection('vizapi')->table('workpoints')->where('alias',$destino)->first();
        $workor = DB::connection('vizapi')->table('orders AS O')->join('workpoints AS W','W.id','O._workpoint_from')->join('product_ordered as PO','PO._order','O.id')->join('products as P','P.id','PO._product')->join('product_prices AS PP','PP._product','P.id')->whereIn('O.id',$order)->where('PP._type',7)->select('W.dominio as IP','W.name AS nombre','W._client as cliente','W.alias as AL','O.name as NOTA')->selectRaw('SUM(ROUND(PP.price * PO.amount,2)) AS TOTAL')->groupByRaw('W.dominio, O.name, W.name, W.alias, W._client')->first();
        $prodor = DB::connection('vizapi')->table('product_ordered AS PO')->join('products AS P','P.id','PO._product')->join('product_prices AS PP','PP._product','PO._product')->whereIn('PO._order',$order)->where('PP._type',7)->select('P.code AS ARTLTR','P.description as DES','PP.price as PRE','PO.amount AS CANLTR', 'P.cost as COSTO')->selectRaw('ROUND(PP.price * PO.amount,2) as TOTAL')->get();
        $domain = $workor->IP;
        $workpoint = $workor->nombre;
        $referencia = "Traspaso A ".$destino;
        $observacion = "Traspaso De ".$workor->AL." A ".$destino." del pedido ".implode("-",$order)." nota ".$workor->NOTA;
        $import = [
            "referencia"=>$referencia,
            "observacion"=>$observacion,
            "total"=>$workor->TOTAL,
            "products"=>$prodor
        ];
        $devolucion = $this->conecStores($domain,'dev',$import,$workpoint);//domain
        if($devolucion['mssg'] === false){
            return response()->json("No hay conexion al servidor ",500);
        }else{
            $iddev = $devolucion['mssg'];
            $devoluc = "DEV. ".$iddev." ".$workpoint;
            $import["referencia"] =  $devoluc;
            $import["cliente"] = $workor->cliente;
            $abono = $this->conecStores('192.168.10.53:1619','abo',$import,$workpoint);//el de cedis
            if($abono['mssg'] === false){
                return response()->json("No hay conexion al servidor ",500);
            }else{
                $idabo = $abono['mssg'];
                $abon = "TRASPASO / SUC ".$workor->AL." ".$destino;
                $import['referencia']= $abon;
                $import["cliente"] = $wordes->cliente;
                $factura = $this->conecStores('192.168.10.53:1619','inv',$import,$workpoint);//el de cedis
                if($factura['mssg'] === false){
                    return response()->json("No hay conexion al servidor ",500);
                }else{
                    $idfac = $factura['mssg'];
                    $desre = "FAC ".$idfac;
                    $import['referencia']=$desre;
                    $facturare = $this->conecStores($wordes->dominio,'invr',$import,$workpoint);//el de origen
                    if($facturare['mssg']=== false){
                        return response()->json("No hay conexion al servidor ",500);
                    }else{
                        $res = [
                            "mssg"=>"TRASPASO EXISTOSO",
                            "origen"=>$workor->AL,
                            "destino"=>$wordes->alias,
                            "FOLIOS"=>[
                                "DEVOLUCION"=>$iddev,
                                "ABONO"=>$idabo,
                                "FACTURA"=>$idfac,
                                "FACTURA REC"=>$facturare['mssg']
                            ],
                            "products"=>$prodor
                        ];
                        return response()->json($res,200);
                    }
                }
            }
        }
    }

    public function conecStores($domain,$rout,$import,$workpoint){

        $url = $domain."/storetools/public/api/Products/".$rout;//se optiene el inicio del dominio de la sucursal
        // $url = $domain."/storetools/public/api/Products/".$rout;//se optiene el inicio del dominio de la sucursal
        // $url = "192.168.10.61:1619"."/storetools/public/api/Products/translate";//se optiene el inicio del dominio de la sucursal
        $ch = curl_init($url);//inicio de curl
        $data = json_encode(["data" => $import]);//se codifica el arreglo de los proveedores
        //inicio de opciones de curl
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        //fin de opciones e curl
        $exec = curl_exec($ch);//se executa el curl
        $exc = json_decode($exec);//se decodifican los datos decodificados
        if(is_null($exc)){//si me regresa un null
            $stor =[ "sucursal"=>$workpoint, "mssg"=>$exec];
        }else{
            // $stor['goals'][] = $store->alias." cambios hechos";//de lo contrario se almacenan en sucursales
            $stor =[ "sucursal"=>$workpoint, "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas
        }
        return $stor;
        curl_close($ch);//cirre de curl
    }

    public function reportDepure(){
        $workpoints = DB::connection('vizapi')->table('workpoints')->where('active',1)->wherein('id',[1,3,4,5,6,7,8,9,10,11,12,13,14,17,18,19,20])->get();
        foreach($workpoints as $workpoint){
            $url = $workpoint->dominio.'/storetools/public/api/Products/reportDepure';

            $ch = curl_init($url);//inicio de curl
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            $exec = curl_exec($ch); // Se ejecuta la solicitud GET
            $exc = json_decode($exec); // Se decodifican los datos decodificados
            // return $exc;
            //fin de opciones e curl
            $ala[] = [$workpoint->alias=>$exc];
            $exec = curl_exec($ch);//se executa el curl
        }

        // return $ala;
        $cedis = $ala[0]['CEDISSAP'];
        $sp1 = $ala[1]['SP1'];
        $sp2 = $ala[2]['SP2'];
        $co1 = $ala[3]['CO1'];
        $co2 = $ala[4]['CO2'];
        $ap1 = $ala[5]['AP1'];
        $ap2 = $ala[6]['AP2'];
        $rc1 = $ala[7]['RC1'];
        $rc2 = $ala[8]['RC2'];
        $br1 = $ala[9]['BR1'];
        $br2 = $ala[10]['BR2'];
        $bol = $ala[11]['BOL'];
        $sp3 = $ala[12]['SP3'];
        $spc = $ala[13]['SPC'];
        $pub = $ala[14]['PUB'];
        $sot = $ala[15]['SOT'];
        $eco = $ala[16]['ECO'];
        // $sp2 = $ala[17]['BR3'];
        $diferencia = array_merge($cedis,$sp1,$sp2,$co1,$co2,$ap1,$ap2,$rc1,$rc2,$br1,$br2,$bol,$sp3,$spc,$pub,$sot,$eco);

        $count = array_count_values($diferencia);

        foreach($count as $element => $count){
            if($count === 17){
                $comun[] = $element;
            }
        }
        return $comun;
        // $cedis = DB::connection('vizapi')->table('workpoints')->where('id',1)->first();
    }

    public function replacecode(Request $request){
        $stor = [];

        $products = $request->all();
        //modificar en mysql
        // foreach($products as $product){
        //     $exist= DB::connection('vizapi')->table('products')->where('code',$product['NUEVO'])->first();
        //     if($exist){
        //         $updnw = DB::connection('vizapi')->table('products')->where('code',$product['NUEVO'])->update(['_status'=>1]);
        //         $updan = DB::connection('vizapi')->table('products')->where('code',$product['ANTERIOR'])->update(['_status'=>4]);
        //     }else{
        //         $updant = DB::connection('vizapi')->table('products')->where('code',$product['ANTERIOR'])->update(['code'=>$product['NUEVO']]);
        //     }
        // }
        $workpoints =DB::connection('vizapi')->table('workpoints')->where('active',1)->get();
        foreach($workpoints as $wrk){
            $domain = $wrk->dominio;
            $url = $domain."/storetools/public/api/Products/replacecode";//se optiene el inicio del dominio de la sucursal
            // $url = "192.168.10.61:1619"."/storetools/public/api/Products/replacecode";//se optiene el inicio del dominio de la sucursal
            $ch = curl_init($url);//inicio de curl
            $data = json_encode(["data" => $products]);//se codifica el arreglo de los proveedores
            //inicio de opciones de curl
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            //fin de opciones e curl
            $exec = curl_exec($ch);//se executa el curl
            $exc = json_decode($exec);//se decodifican los datos decodificados
            if(is_null($exc)){//si me regresa un null
                $stor['fails'] =["sucursal"=>$wrk->alias, "mssg"=>$exec];
                // $stor['fails'] =["sucursal"=>"PRUEBAS", "mssg"=>$exec];

            }else{
                $stor['goals'][] = $wrk->alias." cambios hechos";//de lo contrario se almacenan en sucursales
                // $stor['goals'][] = "PRUEBAS"." cambios hechos";//de lo contrario se almacenan en sucursales
                $stor['goals'] =["sucursal"=>$wrk->alias, "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas
                // $stor['goals'] =["sucursal"=>"PRUEBAS", "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas

            }
            curl_close($ch);//cirre de curl
        }
        return $stor;
    }

    public function trapasDev(Request $request){
        $seguimiento = [
            "SucOrigen"=>null,
            "SucDestino"=>null,
            "Movimientos"=>[
                "Devolucion"=>null,
                "Abono"=>null,
                "FacturaSalida"=>null,
                "FacturaEntrada"=>null,
            ]
        ];
        $cedis =  DB::connection('vizapi')->table('workpoints')->where('id',1)->first();
        $from = DB::connection('vizapi')->table('workpoints')->where('name',$request->origen)->first();
        $seguimiento['SucOrigen']=$from->name;
        $to = DB::connection('vizapi')->table('workpoints')->where('name',$request->destino)->first();
        $seguimiento['SucDestino']=$to->name;
        $dev = $request->devolucion;
        $obs = $request->observacion;
        $import = [
            "dev"=>$dev
        ];
        $getdev = $this->conecStores($from->dominio,'getdev',$import,$from->name);//devolucion
        if($getdev['mssg']===false){
            $msg = [
                "mssg"=>"No hay conexexion a la sucursal origen ".$from->name,
            ];
            return response()->json($msg,500);
        }else{
           $obt =  $getdev["mssg"];
           $seguimiento['Movimientos']['Devolucion']=$obt->devolucion;
           $impabo = [
            "referencia"=>"DEV .".$obt->devolucion." ".$from->alias,
            "cliente"=>$from->_client,
            "observacion"=>$obs,
            "total"=>$obt->total,
            "products"=>$obt->productos
        ];
        $abono = $this->conecStores($cedis->dominio,'abo',$impabo,$from->name);//el de cedis
        if($abono['mssg']===false){
            $msg = [
                "mssg"=>"No hay conexexion a cedis para generar el abono",
            ];
            return response()->json($msg,500);
        }else{
            $obtabo =  $abono["mssg"];
            $seguimiento['Movimientos']['Abono']=$obtabo;
            $impabo['referencia'] = "TRASPASO / SUC ".$from->alias."/".$to->alias;
            $impabo['cliente'] = $to->_client;
            $factura = $this->conecStores($cedis->dominio,'inv',$impabo,$from->name);//el de cedis
            if($factura['mssg']===false){
                $msg = [
                    "mssg"=>"No hay conexexion a cedis para generar la factura"
                ];
                return response()->json($msg,500);
            }else{
                $obtfac = $factura['mssg'];
                $seguimiento['Movimientos']['FacturaSalida'] = $obtfac;
                $impabo['referencia'] = "FAC ".$obtfac;
                $facturare = $this->conecStores($to->dominio,'invr',$impabo,$to->name);//el de DESTINO
                // $facturare = Http::post($to->dominio.'/storetools/public/api/Products/invr',$impabo);
                if($facturare['mssg']===false){
                    $msg = [
                        "mssg"=>"No hay conexexion a la sucursal destino para generar la entrada",
                    ];
                    return response()->json($msg,500);
                }else{
                    $obtfre = $facturare['mssg'];
                    $seguimiento['Movimientos']['FacturaEntrada']=$obtfre;
                }
            }
        }
        return $seguimiento;
        }

    }

    public function invoiceReceived(Request $request){
        $seguimiento = [
            "SucDestino"=>null,
            "Movimientos"=>[
                "FacturaSalida"=>null,
                "FacturaEntrada"=>null,
            ]
        ];
        $cedis = Stores::find(1);
        $fac = $request->factura;
        $import = [
            "fac"=>$fac
        ];
        $getinvoice = Http::post($cedis->ip_address.'/storetools/public/api/Products/getinvoice',$import);
        // $getinvoice = $this->conecStores($cedis->dominio,'getinvoice',$import,$cedis->name);//factura
        $status = $getinvoice->status();
        // $getinvoice = $this->conecStores('192.168.10.154:1619','getinvoice',$import,$cedis->name);//factura
        if($status!=200){
            $msg = [
                "mssg"=>"No hay conexexion a la sucursal origen ".$cedis->name,
            ];
            return response()->json($msg,500);
        }else{
        $dat = $getinvoice->json();
        // return $dat['client'];
        $to = Stores::where('_client',$dat['client'])->first();
        // $to = DB::connection('vizapi')->table('workpoints')->where('_client',$dat['mssg']->client)->first();
        $seguimiento['SucDestino']=$to->name;
        $seguimiento['Movimientos']['FacturaSalida']=$dat['factura'];
        $obs = "Entrada Automatica";
           $impabo = [
            "referencia"=>"FAC ".$dat['factura'],
            "cliente"=>$to->_client,
            "observacion"=>$obs,
            "total"=>$dat['total'],
            "products"=>$dat['productos']
        ];
                // $facturare = $this->conecStores('192.168.12.102:1619','invr',$impabo,$to->name);//el de DESTINO
                // $facturare = $this->conecStores($to->dominio,'invr',$impabo,$to->name);//el de DESTINO
                $facturare = Http::post($to->ip_address.'/storetools/public/api/Products/invr',$impabo);
                $statusre = $facturare->status();
                if($statusre != 200){
                    $msg = [
                        "mssg"=>"No hay conexexion a cedis para generar la entrada",
                    ];
                    return response()->json($msg,500);
                }else{
                    $obtfre = $facturare->json();
                    $insob2 = Http::post($cedis->ip_address.'/storetools/public/api/Resources/updsal',["entrada"=>$obtfre,"salida"=>$fac]);
                    $seguimiento['Movimientos']['FacturaEntrada']=$obtfre;
                }
        return response()->json($seguimiento,200);
        }
    }

    public function trapasAbo(Request $request){
        $seguimiento = [
            "SucOrigen"=>null,
            "SucDestino"=>null,
            "Movimientos"=>[
                "Devolucion"=>null,
                "Abono"=>null
            ]
        ];
        $to = Stores::find(1);
        $from = Stores::find($request->idsuc);
        $seguimiento['SucOrigen']=$from->name;
        $seguimiento['SucDestino']=$to->name;
        $dev = $request->devolucion;
        $obs = $request->observacion;
        $import = [
            "data"=>[
                "dev"=>$dev
            ]
        ];
        $getdev = Http::post($from->ip_address.'/storetools/public/api/Products/getdev',$import);
        // return $getdev['devolucion'];
        $status = $getdev->status();
        if($status == 200){
           $seguimiento['Movimientos']['Devolucion']=$getdev['devolucion'];
           $impabo = [
            "data"=>[
                "referencia"=>"DEV .".$getdev['devolucion']." ".$from->alias,
                "cliente"=>$from->_client,
                "observacion"=>$getdev['referencia'],
                "total"=>$getdev['total'],
                "products"=>$getdev['productos']
            ]
        ];
         $abono = Http::post($to->ip_address.'/storetools/public/api/Products/abo',$impabo);
        // $abono = Http::post('192.168.10.232:1619'.'/storetools/public/api/Products/abo',$impabo);
        $abost = $abono->status();
        if($abost == 200){
            $seguimiento['Movimientos']['Abono']=$abono->json();
            $oab = $abono->json();
            // $insob2 = Http::post($from->ip_address.'/storetools/public/api/Resources/upddev',["abono"=>$obtabo,"devolucion"=>$dev]);
            $insup = ["abono"=>$oab,"devolucion"=>$dev];
            $insob2 = Http::post($from->ip_address.'/storetools/public/api/Resources/upddev',$insup);
            $insta = $insob2->status();
            if($insta == 200){
                return response()->json($seguimiento,200);
            }else{
                return response()->json("Hubo problemas en la actualizacion de el abono",401);
            }
        }else{//abono
            $msg = [
                "mssg"=>$abono->json(),
            ];
            return response()->json($msg,500);
        }
        }else{//getdet
            $msg = [
                "mssg"=>$getdev->json(),
            ];
            return response()->json($msg,500);
        }
        return $seguimiento;
    }

    public function ignoredAbo(Request $request){
        $oab = $request->mssg;
        $dev = $request->devol;
        $suc = $request->idsuc;
        $store = Stores::find($suc);
        $insup = ["abono"=>$oab,"devolucion"=>$dev];
        $insob2 = Http::post($store->ip_address.'/storetools/public/api/Resources/upddev',$insup);
        $status = $insob2->status();
        if($status != 200){
            return response()->json('Hubo un error',401);
        }else{
            return response()->json('mensaje con exito',200);
        }
    }

    public function autoComplete(Request $request){
        $workpoint = $request->_workpoint;
        $query = ProductVA::with([
            // 'units',
            // 'status',
            // 'variants',
            'category.familia.seccion',
            'prices'
            ])->where('_status', '!=', 4)
            ->orderBy('_status', 'asc');
        $autocomplete = $request->autocomplete;
        $search = $request->strict;
        if ($autocomplete && strlen($autocomplete) > 1) {
            if ($search) {
                $query->where(function ($q) use ($autocomplete) {
                    $q->whereHas('variants', fn($q) => $q->where('barcode', $autocomplete))
                    ->orWhere('name', $autocomplete)
                    ->orWhere('barcode', $autocomplete)
                    ->orWhere('code', $autocomplete);
                });
            } else {
                $query->where(function ($q) use ($autocomplete) {
                    $q->whereHas('variants', fn($q) => $q->where('barcode', 'like', "%{$autocomplete}%"))
                    ->orWhere('name', 'like', "%{$autocomplete}%")
                    ->orWhere('code', 'like', "%{$autocomplete}%")
                    ->orWhere('barcode', $autocomplete);
                });
            }
        }
        if ($request->limit) {
            $query->limit($request->limit);
        }
        $products = $search ? $query->first() : $query->get();
        // $products = $query->orderBy('_status', 'asc')->get();
        return response()->json($products);
    }

    public function genshortCode(){
        do {
            // Genera un número aleatorio de 5 dígitos (no comienza con 0)
            $shortcode = mt_rand(10000, 99999);

            // Verifica si ya existe en code, name o barcode de alguna variante
            $exists = ProductVA::where('code', $shortcode)
                ->orWhere('name', $shortcode)
                ->orWhereHas('variants', function ($q) use ($shortcode) {
                    $q->where('barcode', $shortcode);
                })
                ->exists();

        } while ($exists);

        return (string)$shortcode;
    }

    public function genBarcode(Request $request){
        $y = date('Y');
        $caty = $request->id;
        $categoria = ProductCategoriesVA::with('familia.seccion')->find($caty);
        do {
            $randomDigits = '';
            for ($i = 0; $i < 3; $i++) {
                $randomDigits .= mt_rand(0, 9);
            }
            $barcode = $y . $categoria->familia['seccion']['num'] .$categoria->familia['num'] . $categoria->num . $randomDigits;

            $exists = ProductVA::where('barcode', $barcode)
                ->orWhere('code', $barcode)
                ->orwhere('name',$barcode)
                ->orWhereHas('variants', function ($q) use ($barcode) {
                    $q->where('barcode', $barcode);
                })
                ->exists();
        } while ($exists);
        return response()->json($barcode);
    }

    public function searchBarcode($barcode){
        $exists = ProductVA::where('barcode', $barcode)
            ->orWhere('code', $barcode)
            ->orwhere('name',$barcode)
            ->orWhereHas('variants', function ($q) use ($barcode) {
                $q->where('barcode', $barcode);
            })
            ->exists();
        return response()->json($exists,200);

    }
    public function searchCode($id){
        $exists = ProductVA::where('code', $id)
        ->orWhere('name', $id)
        ->orWhereHas('variants', function ($q) use ($id) {
            $q->where('barcode', $id);
        })
        ->exists();
        $res = [
            "exist"=>$exists,
            "cco"=>$this->genshortCode()
        ];
        return response()->json($res,200);
    }

    public function checkCodesBatch(Request $request){
        $codes = $request->input('codes', []);
        $barcodes = $request->input('barcodes', []);

        // Inicializamos los resultados
        $codeResults = [];
        $barcodeResults = [];

        // Procesar los códigos
        foreach ($codes as $code) {
            $exists = ProductVA::where('code', $code)
                ->orWhere('name', $code)
                ->orWhereHas('variants', function ($q) use ($code) {
                    $q->where('barcode', $code);
                })
                ->exists();
            $codeResults[$code] = [
                'exist' => $exists,
                'cco' => $this->genshortCode()
            ];
        }

        // Procesar los códigos de barra
        foreach ($barcodes as $barcode) {
            $exists = ProductVA::where('barcode', $barcode)
                ->orWhere('code', $barcode)
                ->orWhere('name', $barcode)
                ->orWhereHas('variants', function ($q) use ($barcode) {
                    $q->where('barcode', $barcode);
                })
                ->exists();
            $barcodeResults[$barcode] = $exists;
        }

        return response()->json([
            'codes' => $codeResults,
            'barcodes' => $barcodeResults
        ]);
    }

    public function highProducts(Request $request){
        $response=[
            "mysql"=>[
                "insert"=>[
                    "goal"=>[],
                    "fails"=>[]
                ]
            ],
            "sucursales"=>[
                "insert"=>[
                    "goal"=>[],
                    "fails"=>[]
                ]
            ]
        ];
        $insertFactusol = ['productos'=>[]];
        $header = $request->head;
        $data = $request->data;
        $type = 1;//alta de productos

        $control = new ControlFigures;
        $control->name = $header['nameDoc'];
        $control->created_at = $header['date'];
        $control->_type = $type;
        $control->_user = $header['autor']['id'];
        $control->details = json_encode($data);
        $control->save();
        $res=  $control->fresh();
        if($res){
            foreach($data as $product){
                $insertProduct = [
                    'code'=>$product['code'],
                    'name'=>trim($product['short_code']),
                    'description'=>trim($product['description']),
                    'label'=>trim(substr($product["description"],0,30)),
                    'reference'=>$product['reference'],
                    'pieces'=>$product['pxc'],
                    '_category'=>$product['categoria']['id'],
                    '_status'=>1,
                    '_unit'=>$product['umc']['id'],
                    '_provider'=>$product['provider']['id'],
                    'updated_at'=>now(),
                    'created_at'=>now(),
                    'cost'=>$product['cost'],
                    'barcode'=>isset($product['cb']) ? trim($product['cb']) : null,
                    'refillable'=>1,
                    '_maker'=>$product['makers']['id'],
                    'dimensions'=>json_encode(["length"=>'',"height"=>'',"width"=>'']),
                    'large'=>isset($product['mnp']) ? $product['mnp']['large'] : ''
                ];
                $insert = ProductVA::insert($insertProduct);
                if($insert){
                    $insertFactusol['productos'][] = $product;
                    $response['mysql']['insert']['goal'][]=$insertProduct['code'];
                }else{
                    $response['mysql']['insert']['fail'][]=$insertProduct['code'];
                }
            }
            $stores = WorkpointVA::where([['active',1],['id','!=',2]])->get();
            // $stores = WorkpointVA::where('id',1)->get();
            foreach($stores as $store){
                try {
                $createStore = Http::timeout(50)->post($store->dominio.'/storetools/public/api/Products/highProducts',$insertFactusol);
                if($createStore->status() == 200){
                    $response['sucursales']['insert']['goal'][] = [$store->alias=>$createStore->json()];
                }else{
                    $response['sucursales']['insert']['fails'][] = [$store->alias=>['Con Error']];
                }
            } catch (\Throwable $e) {
                    $response['sucursales']['insert']['fails'][] = [
                        $store->alias => ['Sin conexión', 'error' => $e->getMessage()]
                    ];
                }
            }
            return response()->json($response,200);
        }else{
            return response()->json('No se lograron guardar los datos',500);
        }
    }

    public function lookupProducts(Request $request){
        $codes = $request->input('codes');
        $products = ProductVA::whereIn('code', $codes)
        ->with(['category.familia.seccion'])
        ->get();
        return response()->json([
            'products' => $products
        ]);
    }

    public function highPrices(Request $request){
        $response=[
            "mysql"=>[
                "update"=>[
                    "goal"=>[],
                    "fails"=>[]
                ]
            ],
            "sucursales"=>[
                "update"=>[
                    "goal"=>[],
                    "fails"=>[]
                ]
            ]
        ];
        $insertFactusol = ['prices'=>[]];
        $header = $request->head;
        $data = $request->data;
        $type = 2;//cambio de precios
        $control = new ControlFigures;
        $control->name = $header['nameDoc'];
        $control->created_at = $header['date'];
        $control->_type = $type;
        $control->_user = $header['autor']['id'];
        $control->details = json_encode($data);
        $control->save();
        $res =  $control->fresh();
        if($res){
            foreach($data as $price){
                $product = ProductVA::with('prices')->find($price['_product']);
                if($product){
                    $resProduct = historyPricesVA::create([
                        "_product"=>$price['_product'],
                        "created_at"=>now(),
                        "details"=>json_encode(["cost"=>$product->cost, "prices"=>$product->prices]),
                    ]);
                    if($resProduct){
                        $product->cost = $price['costo'];
                        $product->save();
                        $updateProduct = $product->fresh();
                        if($updateProduct){
                            $pivotData = [];
                            foreach ($price['prices'] as $type => $valor) {
                                    $pivotData[$type] = ['price' => $valor];
                            }
                            $pdtPrices = $product->prices()->sync($pivotData);
                            if($pdtPrices){
                                $insertFactusol['prices'][] = $price;
                            }
                        }
                    }
                }
            }
            $stores = WorkpointVA::where('active',1)->whereNotIn('id',[2,18])->get();
            // $stores = WorkpointVA::where('id',1)->get();
            foreach($stores as $store){
                try {
                    // return $store;
                    $createStore = Http::timeout(50)->post($store->dominio.'/storetools/public/api/Products/highPrices',$insertFactusol);
                    // return $createStore;
                    if($createStore->status() == 200){
                        $response['sucursales']['update']['goal'][] = [$store->alias=>$createStore->json()];
                    }else{
                        $response['sucursales']['update']['fails'][] = [$store->alias=>['Con Error']];
                    }
                } catch (\Throwable $e) {
                    $response['sucursales']['update']['fails'][] = [
                        $store->alias => ['Sin conexión', 'error' => $e->getMessage()]
                    ];
                }
            }

            $dionisio = [
                [
                    "dominio"=>'ipwasabd-ntgkpkdcrv.dynamic-m.com:1620',
                    "alias"=>"GR2",
                ],
                [
                    "dominio"=>'novedadesdio-tkkhkmjbrv.dynamic-m.com:1619',
                    "alias"=>"GR1",

                ]
            ];
            foreach($dionisio as $st){
                 try {
                $createStoreDio = Http::timeout(50)->post($st['dominio'].'/storetools/public/api/Products/highPrices',$insertFactusol);
                // $createStoreDio = Http::post('192.168.10.160:1619'.'/storetools/public/api/Products/highPrices',$insertFactusol);
                    if($createStoreDio->status() == 200){
                        $response['sucursales']['update']['goal'][] = [$st['alias']=>$createStoreDio->json()];
                    }else{
                        $response['sucursales']['update']['fails'][] = [$st['alias']=>['Con Error']];
                    }
                } catch (\Throwable $e) {
                    $response['sucursales']['update']['fails'][] = [
                        $st['alias'] => ['Sin conexión', 'error' => $e->getMessage()]
                    ];
                }
            }


            $foraneo = WorkpointVA::find(18);
            try {
                $createStoreFor = Http::timeout(50)->post($foraneo->dominio.'/storetools/public/api/Products/regispricefor',$insertFactusol);
                // $createStoreFor = Http::post('192.168.10.160:1619'.'/storetools/public/api/Products/regispricefor',$insertFactusol);
                // return $createStoreFor;
                if($createStoreFor->status() == 200){
                    $response['sucursales']['update']['goal'][] = [$foraneo->alias=>$createStoreFor->json()];
                }else{
                    $response['sucursales']['update']['fails'][] = [$foraneo->alias=>['Con Error']];
                }
            } catch (\Throwable $e) {
                $response['sucursales']['update']['fails'][] = [
                $foraneo->alias => ['Sin conexión', 'error' => $e->getMessage()]
                ];
            }
            return response()->json($response);
        }else{
            return response()->json('No se lograron guardar los datos',500);
        }
    }

    public function checkLabels(Request $request){
        $wrk  = $request->workpoint;
        $qrs = $request->products; // Array de QR como strings
        $results = [];
        foreach ($qrs as $qr) {
            if (!$qr) {
                $results[] = [
                    'code' => $qr,
                    'status' => false,
                    'message' => 'QR inválido'
                ];
                continue;
            }
            $product = ProductVA::with([
                'historicPrices' => fn($q)=>$q->latest('created_at')->limit(1),
                'stocks' => fn($q) => $q->where('id',5),
                'locations' => fn($q) =>  $q->whereHas('celler', fn($q2) => $q2->where([['_workpoint', 5],['_type',2]])),
                'prices' => fn($q) => $q->whereIn('_type', [1,2,3,4])->orderBy('_type'),
            ])
            ->find($qr['modelo']);
            if (!$product) {
                $results[] = [
                    'code' => $qr['modelo'],
                    'status' => false
                ];
                continue;
            }
            $latestHistory = $product->historicPrices()->orderBy('id', 'desc')->first();
            if ($latestHistory) {
                $status = ($latestHistory->id == $qr['idChange']) ? true : false;
                $results[] = [
                    'code' => $product,
                    'status' => $status,
                    'actualizado' => $latestHistory
                ];
            } else {
                $results[] = [
                    'code' => $product,
                    'status' => false,
                    'actualizado' => null
                ];
            }
        }
        return response()->json($results);
    }

    public function setMin(Request $request){
        // return $request->product;
        $updated = ProductStockVA::where('_product', $request->product)
            ->where('_workpoint', $request->_workpoint)
            ->update(['min' => $request->min]);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Mínimo actualizado correctamente'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro para actualizar'
            ]);
        }
    }

    public function setMax(Request $request){
        $updated = ProductStockVA::where('_product', $request->product)
            ->where('_workpoint', $request->_workpoint)
            ->update(['max' => $request->max]);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Maximo actualizado correctamente'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro para actualizar'
            ]);
        }
    }

    public function setMassisveMinMax(Request $request){
        $actualizados = [
            "goals"=>0,
            "fails"=>0
        ];
        $products = $request->products;
        $workpoint = $request->workpoint;
        foreach($products as $product){
            $updated = ProductStockVA::where('_product', $product['id'])
                ->where('_workpoint', $workpoint)
                ->update(['max' => $product['max'], 'min' => $product['min'] ]);
            if ($updated) {
                $actualizados['goals']++;
            } else {
                $actualizados['fails']++;
            }
        }
        return response()->json($actualizados,200);

    }
}
