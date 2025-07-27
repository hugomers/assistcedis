<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ProductVA;
use App\Models\Stores;

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
        $product->total_stock = $product->stocks->sum(fn($item) => $item->pivot->stock);
        $product->details = $product->combinedAmountByYear();
        return $product;
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

    // public function autoComplete(Request $request){ // Función autocomplete 2.0
    //     $workpoint = $request->_workpoint;
    //     $query = ProductVA::with(['category.familia.seccion','prices']);
    //     if(isset($request->autocomplete) && $request->autocomplete){ //Valida si se utilizara la función de autocompletado ?
    //         $codes = explode('ID-', $request->autocomplete); // Si el codigo tiene ID- al inicio la busqueda sera por el id que se le asigno en el catalog maestro (tabla products)
    //         if(count($codes)>1){
    //             $query = $query->where('id', $codes[1]);
    //         }elseif(isset($request->strict) && $request->strict){ //La coincidencia de la busqueda sera exacta
    //             if(strlen($request->autocomplete)>1){
    //                 $query = $query->whereHas('variants', function(Builder $query) use ($request){
    //                     $query->where('barcode', $request->autocomplete);
    //                 })
    //                 ->orWhere(function($query) use($request){
    //                     $query
    //                     ->orWhere('name', $request->autocomplete)
    //                     ->orWhere('barcode', $request->autocomplete)
    //                     ->orWhere('code', $request->autocomplete);
    //                 });
    //             }
    //         }
    //         else{ //La busqueda se realizara por similitud
    //             if(strlen($request->autocomplete)>1){
    //                 $query = $query->whereHas('variants', function(Builder $query) use ($request){
    //                     $query->where('barcode', 'like', '%'.$request->autocomplete.'%');
    //                 })
    //                 ->orWhere(function($query) use($request){
    //                     $query->orWhere('name', $request->autocomplete)
    //                     ->orWhere('barcode', $request->autocomplete)
    //                     ->orWhere('code', $request->autocomplete)
    //                     ->orWhere('name', 'like','%'.$request->autocomplete.'%')
    //                     ->orWhere('code', 'like','%'.$request->autocomplete.'%');
    //                 });
    //             }
    //         }
    //     }
    //     $query = $query->where("_status", "!=", 4);
    //     if(isset($request->products) && $request->products){ //Se puede buscar mas de un codigo a la vez mendiente el parametro products
    //         $query = $query->whereHas('variants', function(Builder $query) use ($request){
    //             $query->whereIn('barcode', $request->products);
    //         })
    //         ->orWhereIn('name', $request->products)
    //         ->orWhereIn('code', $request->product);
    //     }

    //     if(isset($request->_category)){ //Se puede realizar una busqueda con el filtro de sección, familia, categoría mediente el ID de lo que estamos buscando
    //         $_categories = $this->getCategoriesChildren($request->_category); // Se obtiene los hijos de esa categoría
    //         $query = $query->whereIn('_category', $_categories); // Se añade el filtro de la categoría para realizar la busqueda
    //     }

    //     if(isset($request->_status)){ // Se puede realizar una busqueda con el filtro de status del producto mediante el ID del status que estamos buscando
    //         $query = $query->where('_status', $request->_status); // Se añade el filtro de la categoría para realizar la busqueda
    //     }

    //     if(isset($request->_location)){ //Se puede realizar una busqueda con filtro de ubicación del producto mediante el ID de la ubicación (sección, pasillo, tarima, etc) que estamos buscando
    //         $_locations = $this->getSectionsChildren($request->_location); //Se obtienen todos los hijos de la sección de la busqueda para realizar la busqueda completa
    //         $query = $query->whereHas('locations', function( Builder $query) use($_locations){
    //             $query->whereIn('_location', $_locations); // Se añade el filtro de la sección para realizar la busqueda
    //         });
    //     }

    //     if(isset($request->_celler) && $request->_celler){ // Se puede realizar una busqueda con filtro de almacen
    //         $locations = \App\CellerSection::where([['_celler', $request->_celler],['deep', 0]])->get(); // Se obtiene todas las ubicaciones dentro del almacen
    //         $ids = $locations->map(function($location){
    //             return $this->getSectionsChildren($location->id);
    //         });
    //         $_locations = array_merge(...$ids); // Se genera un arreglo con solo los ids de las ubicaciones
    //         $query = $query->whereHas('locations', function( Builder $query) use($_locations){
    //             $query->whereIn('_location', $_locations);
    //         });
    //     }

    //     if(isset($request->check_sales)){
    //         //OBTENER FUNCIÓN DE CHECAR STOCKS
    //     }

    //     $query = $query->with(['units', 'status', 'variants']); // por default se obtienen las unidades y el status general
    //     if(isset($request->_workpoint_status) && $request->_workpoint_status){ // Se obtiene el stock de la tienda se se aplica el filtro

    //         if($request->_workpoint_status == "all"){
    //             $query = $query->with(['stocks']);
    //         }else{
    //             $workpoints = $request->_workpoint_status;
    //             $workpoints[] = 1; // Siempre se agrega el status de la sucursal
    //             $query = $query->with(['stocks' => function($query) use($workpoints){ //Se obtienen los stocks de todas las sucursales que se pasa el arreglo
    //                 $query->whereIn('_workpoint', $workpoints)->distinct();
    //             }]);
    //         }
    //     }else{
    //         $query = $query->with(['stocks' => function($query) use($workpoint){ //Se obtiene el stock de la sucursal
    //             $query->where('_workpoint', $workpoint)->distinct();
    //         }]);
    //     }

    //     if(isset($request->with_locations) && $request->with_locations){ //Se puede agregar todas las ubicaciones de la sucursal
    //         $query = $query->with(['locations' => function($query) use ($workpoint) {
    //             $query->whereHas('celler', function($query) use ($workpoint) {
    //                 $query->where([['_workpoint', $workpoint],['_type',2]]);
    //             });
    //         }]);
    //     }

    //     if(isset($request->check_stock) && $request->check_stock){ //Se puede agregar el filtro de busqueda para validar si tienen o no stocks los productos
    //         if($request->with_stock){
    //             $query = $query->whereHas('stocks', function(Builder $query) use($workpoint){
    //                 $query->where('_workpoint', $workpoint)->where('stock', '>', 0); //Con stock
    //             });
    //         }else{
    //             $query = $query->whereHas('stocks', function(Builder $query) use($workpoint){
    //                 $query->where('_workpoint', $workpoint)->where('stock', '<=', 0); //Sin stock
    //             });
    //         }
    //     }

    //     if(isset($request->with_prices) && $request->with_prices){ //Se puede agregar los precios de lista del producto
    //         $query = $query->with(['prices' => function($query){
    //             $query->whereIn('_type', [1, 2, 3, 4])->orderBy('id'); //Solo se envian los precios de Menudeo, Mayoreo, Docena o Media caja y caja
    //         }]);
    //     }
    //     if(isset($request->with_prices_Invoice) && $request->with_prices_Invoice){
    //         $query = $query->with(['prices' => function($q) { $q->where('id',7); } ]);
    //     }
    //     if(isset($request->limit) && $request->limit){ //Se puede agregar un limite de los resultados mostrados
    //         $query = $query->limit($request->limit);
    //     }
    //     if(isset($request->paginate) && $request->paginate){
    //         $products = $query->orderBy('_status', 'asc')->paginate($request->paginate);
    //     }else{
    //         $products = $query->orderBy('_status', 'asc')->get();
    //     }
    //     return response()->json($products);
    // }

    public function autoComplete(Request $request){
        $workpoint = $request->_workpoint;
        $query = ProductVA::with(['category.familia.seccion', 'prices']);

        $autocomplete = $request->autocomplete;

        // --- AUTOCOMPLETE SEARCH ---
        if ($autocomplete && strlen($autocomplete) > 1) {
            $codes = explode('ID-', $autocomplete);
            if (count($codes) > 1) {
                $query->where('id', $codes[1]);
            } elseif ($request->strict) {
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

        // --- PRODUCT STATUS FILTER ---
        $query->where('_status', '!=', 4);

        // --- SEARCH MULTIPLE PRODUCTS ---
        if (!empty($request->products)) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('variants', fn($q) => $q->whereIn('barcode', $request->products))
                ->orWhereIn('name', $request->products)
                ->orWhereIn('code', $request->products);
            });
        }

        // --- CATEGORY FILTER ---
        if ($request->_category) {
            $_categories = $this->getCategoriesChildren($request->_category);
            $query->whereIn('_category', $_categories);
        }

        // --- STATUS FILTER ---
        if ($request->_status !== null) {
            $query->where('_status', $request->_status);
        }

        // --- LOCATION FILTER ---
        if ($request->_location) {
            $_locations = $this->getSectionsChildren($request->_location);
            $query->whereHas('locations', fn($q) => $q->whereIn('_location', $_locations));
        }

        // --- CELLER FILTER ---
        if ($request->_celler) {
            $locations = \App\CellerSection::where('_celler', $request->_celler)->where('deep', 0)->get();
            $_locations = $locations->flatMap(fn($l) => $this->getSectionsChildren($l->id))->all();
            $query->whereHas('locations', fn($q) => $q->whereIn('_location', $_locations));
        }

        // --- STOCK BY WORKPOINT ---
        if ($request->_workpoint_status) {
            if ($request->_workpoint_status === 'all') {
                $query->with('stocks');
            } else {
                $workpoints = is_array($request->_workpoint_status) ? $request->_workpoint_status : [$request->_workpoint_status];
                $workpoints[] = 1;
                $query->with(['stocks' => fn($q) => $q->whereIn('_workpoint', $workpoints)->distinct()]);
            }
        } else {
            $query->with(['stocks' => fn($q) => $q->where('_workpoint', $workpoint)->distinct()]);
        }

        // --- LOCATIONS BY WORKPOINT ---
        if ($request->with_locations) {
            $query->with(['locations' => function ($q) use ($workpoint) {
                $q->whereHas('celler', fn($q) => $q->where('_workpoint', $workpoint)->where('_type', 2));
            }]);
        }

        // --- STOCK FILTER ---
        if ($request->check_stock) {
            $query->whereHas('stocks', function ($q) use ($workpoint, $request) {
                $stockOperator = $request->with_stock ? '>' : '<=';
                $q->where('_workpoint', $workpoint)->where('stock', $stockOperator, 0);
            });
        }

        // --- PRICES FILTER ---
        if ($request->with_prices) {
            $query->with(['prices' => fn($q) => $q->whereIn('_type', [1, 2, 3, 4])->orderBy('id')]);
        }

        if ($request->with_prices_Invoice) {
            $query->with(['prices' => fn($q) => $q->where('id', 7)]);
        }

        // --- EXTRA RELATIONS ---
        $query->with(['units', 'status', 'variants']);

        // --- LIMIT OR PAGINATE ---
        if ($request->paginate) {
            $products = $query->orderBy('_status', 'asc')->paginate($request->paginate);
        } else {
            if ($request->limit) {
                $query->limit($request->limit);
            }
            $products = $query->orderBy('_status', 'asc')->get();
        }

        return response()->json($products);
    }

    // public function searchExact(Request $request){
    //     $term = strtoupper(trim($request->autocomplete));
    //     $workpoint = $request->_workpoint;

    //     $query = ProductVA::query()
    //         ->with([
    //             'category.familia.seccion',
    //             'prices',
    //             'status',
    //             'units',
    //             'variants',
    //             'stocks' => fn($q) => $q->where('_workpoint', $workpoint),
    //         ])
    //         ->where(function ($q) use ($term) {
    //             $q->where('code', $term)
    //             ->orWhere('name', $term)
    //             ->orWhere('barcode', $term)
    //             ->orWhereHas('variants', fn($q) => $q->where('barcode', $term));
    //         })
    //         ->where('_status', '!=', 4)
    //         ->limit(1);

    //     return response()->json($query->get());
    // }



}
