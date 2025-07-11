<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
}
