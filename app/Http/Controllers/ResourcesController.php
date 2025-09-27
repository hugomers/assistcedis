<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Solicitudes;
use App\Models\Stores;
use App\Models\transfer;
use App\Models\Staff;
use App\Models\ReplyClient;
use App\Models\Flight;
use App\Models\ClientVA;


use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\RequestException;

class ResourcesController extends Controller
{
    public function actasAdministrativas(Request $request){
        $data =  $request->all();
        $pdf = $this->pdfacd($data);
        return response()->json($pdf);
    }

    public function pdfacd($data){
        $res = [];
        $items = $data;
        $hora =date('H:i:s', strtotime($items['fecha']) - 3600);
        $horanom =date('H_i_s', strtotime($items['fecha']) - 3600);
        $fecha =date('Y-m-d', strtotime($items['fecha']));
        $fechapdf = $items['fecha'];
        $horapdf = $items['hora'];
        $sucursal = $items['sucursal'];
        switch($sucursal){
            case "SAN PABLO 1":
            $domicilio = "San Pablo #10. Loc. G. Col. Centro Cuauhtémoc. CDMX";
            break;
            case "SAN PABLO 2":
            $domicilio = "San Pablo #10 Loc. A y B. Col. Centro, Cuauhtémoc. CDMX";
            break;
            case "SAN PABLO 3":
            $domicilio = "San Pablo #10 Loc. A y B. Col. Centro, Cuauhtémoc. CDMX";
            break;
            case "SAN PABLO C":
            $domicilio = "San Pablo #10 Loc. C. Col. Centro, Cuauhtémoc CDMX";
            break;
            case "SOTANO":
            $domicilio = "San Pablo #10. Planta Baja. Col. Centro CDMX";
            break;
            case "CORREO 1":
            $domicilio = "Correo Mayor #84 Col. Centro Cuauhtémoc. CDMX";
            break;
            case "CORREO 2":
            $domicilio = "Correo Mayor #122 Col. Centro, Cuauhtémoc. CDMX";
            break;
            case "RAMON CORONA 1":
            $domicilio = "Ramón Corona #15. Loc. B. Col. Centro, Cuauhtémoc";
            break;
            case "RAMON CORONA 2":
            $domicilio = "Ramón Corona #15. Loc. C. Col. Centro, Cuauhtémoc";
            break;
            case "BOLIVIA":
            $domicilio = "Rep. Bolivia #15. Col. Centro, Cuauhtémoc CDMX";
            break;
            case "BRASIL 1":
            $domicilio = "Rep. Brasil #62 Col. Centro, Cuauhtémoc. CDMX";
            break;
            case "BRASIL 2":
            $domicilio = "Rep. Brasil #60 Col. Centro, Cuauhtémoc CDMX";
            break;
            case "BRASIL 3":
            $domicilio = "Rep. Brasil #60 Col. Centro, Cuauhtémoc CDMX";
            break;
            case "APARTADO 1":
            $domicilio = "Apartado #34 Loc. 2 y 3 Col. Centro, Cuauhtémoc CDMX";
            break;
            case "APARTADO 2":
            $domicilio = "Apartado #32 Col. Centro, Cuauhtémoc CDMX";
            break;
            case "PUEBLA":
            $domicilio = "Av 10 poniente #318 colonia centro entre 5 y 3 norte";
            break;
            case "CEDIS":
            $domicilio = "San Pablo #10. Planta Baja. Col. Centro CDMX";
            break;
        }
        $nombre = $items['miembro'];
        $puesto = $items['puesto'];
        $motivo = $items['motivo'];
        $defensacol = $items['consmie'];
        $respuestacompa = $items['contes'];
        $conclusion = $items['conclusion'];

        $carpaud = "C:\REPORTESCHKL\ACTASADMINISTRATIVA";
        $creapd = [
            "hora"=>$horapdf,
            "fecha"=>$fechapdf,
            "sucursal"=>$sucursal,
            "domicilio"=>$domicilio,
            "nombre"=>$nombre,
            "puesto"=>$puesto,
            "motivo"=>$motivo,
            "defensacol"=>$defensacol,
            "respuestacompa"=>$respuestacompa,
            "conclusion"=>$conclusion
        ];
            $pdf = View::make('actas', $creapd)->render();

            // $number = "5573461022";


            $options = new Options();
            $options->set('isRemoteEnabled',true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdf);
            $dompdf->render();
            $output = $dompdf->output();
            $namefile = 'actaadm'.$nombre."_".$fecha."_".$horanom.'.pdf';
            $rutacompleta = $carpaud.'/'.$namefile;
            file_put_contents($rutacompleta,$output);
            $filename = $this->sendocument($rutacompleta,$namefile);
            if($filename == "enviado"){
                $res [] = [
                    "msg"=>"enviado",
                    "archivo"=>$rutacompleta
                ];
            }else{
                $res [] = [
                    "msg"=>"no enviado",
                    "archivo"=>$rutacompleta
                ];
            }
            return response()->json($res,200);
    }

    public function sendocument($archivo,$namefile){
        $token = env('WATO');
        $number = "5539297483";
        // $number = "5573461022";

        $data = file_get_contents($archivo);
        $file = base64_encode($data);

        $params=array(
            'token' => $token,
            'to' => $number,
            'filename' => $namefile,
            'document' => $file,
            'caption' => 'Se envia el acta administrativa '.$namefile
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/instance9800/messages/document",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
            return "cURL Error #:" . $err;
            } else {
            return "enviado";
            }
    }

    public function Index(){
        $agent = Staff::where('acitve',1)->get();
        $client = ClientVA::where('id','>=',36)->get();
        $res = [
            "agents"=>$agent,
            "clients"=>$client,
        ];
        if($res){
            return response()->json($res,200);
        }else{
            return response()->json("No hay error",501);
        }
    }

    public function Create(Request $request){
        $address = json_decode($request->address,true);
        $ins = [
            "nom_cli"=>$request->name,
            "celphone"=>$request->phone,
            "email"=>$request->email,
            "tickets"=>$request->ticket,
            "_store"=>$request->branch,
            "price"=>$request->priceList,
            "notes"=>$request->notes,
            "_status"=>0,
            "street"=>$address['street'],
            "num_int"=>$address['numint'],
            "num_ext"=>$address['numext']?? null,
            "col"=>$address['colinia'],
            "mun"=>$address['mun'],
            "estado"=>$address['state'],
            "cp"=>$address['cp']
        ];
        $form = new Solicitudes($ins);
        $form->save();
        $res = $form->fresh()->toArray();
        if($res){
            if ($request->hasFile('picture')) {
                $avatar = $request->file('picture');
                $fileName = uniqid().'.'.$avatar->getClientOriginalExtension();
                $folderPath = 'vhelpers/client/'.$fileName;
                $route = Storage::put($folderPath, file_get_contents($avatar));
                $form->picture = $fileName;
                $form->save();
                    return response()->json(["mssg"=>"Solicitud Creada"], 200);
            }
        } else {
            return response()->json(["mssg"=>"No se pudo enviar el formulario"], 500);
        }
    }

    public function getSolicitud(){
        $solicitudes = DB::table('forms AS F')->join('stores AS S','S.id','F._store')->leftjoin('reply_client as rc','rc._form','F.id')->select('F.*','S.name AS sucursal','rc.reply_workpoints as Stores')->get();
        $res = [
            "solicitudes"=>$solicitudes,
        ];

        if($res){
            return response()->json($res,200);
        }else{
            return response()->json("No hay error",501);
        }
    }

    public function createClient(Request $request){
        $sol = Solicitudes::find($request->id);
        if($sol){
            $cedis = Stores::find(1);
            $ip = $cedis->ip_address;
            // $ip = '192.168.10.160:1619';
            $addcli = Http::post($ip.'/storetools/public/api/Resources/createClient',$request->all());
            $respuesta =  $addcli->status();
            if($respuesta == 400){
                $mssg = ["mssg"=>"hubo un error","error"=>$addcli->json()];
                return response()->json($mssg,400);
            }else{
                $respuesta = $addcli->json();
                $ifsol = $respuesta['id'];
                $sol->_status = 1;
                $sol->fs_id = $ifsol;
                $sol->save();
                $res = $sol->fresh()->toArray();

                $client = $request->all();

                $ins = [
                    'id' => intval($ifsol),
                    'name' => mb_convert_encoding((string)($client['nom_cli'] ?? ''), "UTF-8", "Windows-1252"),
                    'phone' => mb_convert_encoding((string)($client['celphone'] ?? ''), "UTF-8", "Windows-1252"),
                    'email' => mb_convert_encoding((string)($client['email'] ?? ''), "UTF-8", "Windows-1252"),
                    'rfc' => '',
                    'address' => json_encode([
                        "calle" => mb_convert_encoding((string)($client['street'] ?? ''), "UTF-8", "Windows-1252"),
                        "colonia" => mb_convert_encoding((string)($client['col'] ?? ''), "UTF-8", "Windows-1252"),
                        "cp" => intval($client['cp'] ?? 0),
                        "municipio" => mb_convert_encoding((string)($client['mun'] ?? ''), "UTF-8", "Windows-1252")
                    ]),
                    'picture'=>$client['picture'],
                    '_price_list' => intval($client['price'] ?? 0),
                    "created_at" => now()
                ];
                $insDB = DB::connection('vizapi')->table('client')->insert($ins);
                return response()->json($res,201);
            }
        }else{return response()->json("no existe el id",404);
        }
    }

    public function updateImageClient(Request $request){
        $client = ClientVA::find($request->id);
        if($client){
            if ($request->hasFile('picture')) {
                $avatar = $request->file('picture');
                $fileName = uniqid().'.'.$avatar->getClientOriginalExtension();
                $folderPath = 'vhelpers/client/'.$fileName;
                $route = Storage::put($folderPath, file_get_contents($avatar));
                $client->picture = $fileName;
                $client->save();
                return response()->json(["mssg"=>"Imagen Guardada","image"=>$fileName], 200);
            }
        }else{
            return response()->json(["mssg"=>"No se encuentra el cliente"],404);
        }

    }

    public function IgnoredClient(Request $request){
        $sol = Solicitudes::find($request->id);
        if($sol){
            $sol->_status = 2;
            $sol->save();
            $res = $sol->fresh()->toArray();

            return response()->json($res,201);
        }else{return response()->json("no existe el id",404);}

    }

    public function Delete(Request $request){
        $sol = Solicitudes::find($request->id);//route
        if($sol){
            $sol->_status = 4;
            $sol->save();
            $res = $sol->fresh()->toArray();

            return response()->json($res,201);
        }else{return response()->json("no existe el id",404);}
    }

    public function Restore(Request $request){
        $sol = Solicitudes::find($request->id);
        if($sol){
            $sol->_status = 0;
            $sol->save();
            $res = $sol->fresh()->toArray();

            return response()->json($res,201);
        }else{return response()->json("no existe el id",404);}
    }

    public function getsol(){
        $sol = Solicitudes::find(3)->stores->name;
        return $sol;
    }

    public function conecStores($domain,$rout,$import,$workpoint){

        $url = $domain."/storetools/public/api/Resources/".$rout;//se optiene el inicio del dominio de la sucursal
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

    public function getSldas(){
        $ip = Stores::find(1);
        // $ip = '192.168.12.102:1619';
        $getsal = Http::get($ip->ip_address.'/storetools/public/api/Resources/getsal');
        return $getsal;
    }

    public function getEnts(){
        // $ip = Stores::all();
        $ip = '192.168.10.232:1619';
        $getsal = Http::get($ip.'/storetools/public/api/Resources/getsal');
        return $getsal;
    }

    public function getclient(Request $request){
        $find = $request->query('q');
        $cedis = Stores::find(1);
        $getsal = Http::get($cedis->ip_address.'/storetools/public/api/Resources/getclient?q='.$find);
        return $getsal;
    }

    public function syncClient(){
        $res = [];
        $solicitudes = Solicitudes::where('_status',1)->get();
        if($solicitudes){
            $stores = Stores::WhereNotIn('id',[1,2,5,14,15])->get();
            foreach($solicitudes as $solicitud){
                $wrk = [];
                foreach($stores as $store){
                    $ip = $store->ip_address;
                    // $ip = '192.168.10.232:1619';
                    try{
                        $inscli = Http::post($ip.'/storetools/public/api/Resources/createClientSuc',$solicitud);
                        $status = $inscli->status();
                        if($status == 201){
                            $wrk[] = $store->alias;
                        }

                    }catch(\Exception $e) {
                        // Registrar el error y continuar
                        error_log("Error en la URL {$ip}: " . $e->getMessage());
                        // $wrk[] = null;
                    }

                }
                $reply = ReplyClient::upsert([
                    ['_form'=>$solicitud->id,'reply_workpoints'=>json_encode($wrk)],
                    ['_form'=>$solicitud->id,'reply_workpoints'=>json_encode($wrk)]],
                    ['reply_workpoints']
                );


                if(count($wrk) == count($stores)){
                    $updsol = Solicitudes::find($solicitud->id);
                    $updsol ->_status = 3;
                    $updsol ->save();
                    $std = $updsol->fresh()->toArray();
                    $solicitud  = Solicitudes::find($solicitud->id);
                }
            }
            $res = DB::table('forms as f')->join('reply_client as rc','rc._form','f.id')->where('f.id',$solicitud->id)->select('f.*','rc.reply_workpoints as Stores')->get();
        return $res;
        }
    }

    public function getSuc(){
        $stores = Stores::all();
        return response()->json($stores);
    }

    public function getDev(Request $request){
        $stores = Stores::find($request->id);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.182:1619';
        $getdev = Http::get($ip.'/storetools/public/api/Resources/getdev');
        return $getdev;
    }

    public function gettras(Request $request){
        $anio = date('Y');
        $stores = Stores::find($request->id);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.112:1619';
        $ge = transfer::where('_store_from',$stores->id)->whereYear('created_at', $anio)->get();
        $getdev = Http::get($ip.'/storetools/public/api/Resources/gettras');
        $data = $getdev->json();
        $rees = [
            'devoluciones'=>$data,
            'movimientos'=>$ge
        ];
        return $rees;
    }

    public function iniproces(Request $request){
        $transfer = new transfer;
        $transfer->_store_from = $request->from['id'];
        $transfer->_store_to = $request->to['id'];
        $transfer->refund = $request->devolucion['DEVOLUCION'];
        $transfer->provider = $request->devolucion['PROVEEDOR'];
        $transfer->reference = $request->devolucion['REFERENCIA'];
        $transfer->save();
        $res = $transfer->fresh()->toArray();
        if($res){
            $devolucion = $this->busDev($request->from['id'],$request->devolucion['DEVOLUCION']);
            if($devolucion){
                $impabo = [
                    "referencia"=>"DEV .".$request->devolucion['DEVOLUCION']." ".$request->from['alias'],
                    "cliente"=>$request->from['_client'],
                    "observacion"=>'Traspaso a '.$request->to['alias'],
                    "total"=>$devolucion['total'],
                    "products"=>$devolucion['productos']
                ];
                $abono = $this->abono($impabo);
                if($abono){
                    $updseason = transfer::find($res['id']);
                    $updseason->season_ticket = str_replace('"','',$abono);
                    $updseason->save();
                    $return = $updseason->fresh()->toArray();
                    if($return){
                        $impabo['referencia'] = "TRASPASO / SUC ".$request->from['alias']."/".$request->to['alias'];
                        $impabo['cliente'] = $request->to['_client'];
                        $salida = $this->salida($impabo);
                        if($salida){
                            $updinvoice = transfer::find($res['id']);
                            $updinvoice->invoice = str_replace('"','',$salida);
                            $updinvoice->save();
                            $resinvoice = $updinvoice->fresh()->toArray();
                            if($resinvoice){
                                $impabo['referencia'] = "FAC ".str_replace('"','',$salida);
                                $entrada = $this->entry($request->to['id'],$impabo);
                                if($entrada){
                                    $updentry = transfer::find($res['id']);
                                    $updentry->entry = str_replace('"','',$entrada);
                                    $updentry->save();
                                    $reenrada = $updentry->fresh()->toArray();
                                    return response()->json($reenrada,200);
                                }else{
                                    return response()->json($resinvoice,200);
                                }
                            }
                        }else{
                            return response()->json($return,200);
                        }
                    }
                }else{
                    return response()->json($res,200);
                }
            }else{
                return response()->json('No Existe la devolucion',404);
            }
        }
    }

    public function busDev($from, $devolucion){
            $stores = Stores::find($from);
            $ip = $stores->ip_address;
            // $ip = '192.168.10.112:1619';
            $getdev = Http::post($ip.'/storetools/public/api/Resources/returndev',$devolucion);
            if($getdev->status() != 200){
                return false;
            }else{
                return $getdev;
            }
    }

    public function abono($impabo){
        $stores = Stores::find(1);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.112:1619';
        $getdev = Http::post($ip.'/storetools/public/api/Resources/createAbono',$impabo);
            if($getdev->status() != 200){
                return false;
            }else{
                return $getdev;
            }
    }

    public function salida($impabo){
        $stores = Stores::find(1);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.112:1619';
        $getdev = Http::post($ip.'/storetools/public/api/Resources/createSalidas',$impabo);
            if($getdev->status() != 200){
                return false;
            }else{
                return $getdev;
            }
    }

    public function entry($to,$impabo){
        $stores = Stores::find($to);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.112:1619';
        $getdev = Http::post($ip.'/storetools/public/api/Resources/createEntradas',$impabo);
            if($getdev->status() != 200){
                return false;
            }else{
                return $getdev;
            }
    }

    public function nabo(Request $request){
        $from = Stores::find($request->_store_from);
        $to = Stores::find($request->_store_to);
            $devolucion = $this->busDev($from->id,$request->refund);
            if($devolucion){
                $impabo = [
                "referencia"=>"DEV .".$request->refund." ".$from->alias,
                "cliente"=>$from->_client,
                "observacion"=>'Traspaso a '.$to->alias,
                "total"=>$devolucion['total'],
                "products"=>$devolucion['productos']
                ];

                $abono = $this->abono($impabo);
                if($abono){
                    $updseason = transfer::find($request->id);
                    $updseason->season_ticket = str_replace('"','',$abono);
                    $updseason->save();
                    $return = $updseason->fresh()->toArray();
                    return response()->json($return,200);
                }else{
                return response()->json("No se realizo el abono",404);
                }
            }else{
                return response()->json('No Existe la devolucion',404);
            }
    }

    public function ninv(Request $request){
        $from = Stores::find($request->_store_from);
        $to = Stores::find($request->_store_to);
        $devolucion = $this->busDev($from->id,$request->refund);
        if($devolucion){
            $impabo = [
                "referencia"=>"TRASPASO / SUC ".$from->alias." / ".$to->alias,
                "cliente"=>$to->_client,
                "observacion"=>'Traspaso a '.$to->alias,
                "total"=>$devolucion['total'],
                "products"=>$devolucion['productos']
                ];
                    $salida = $this->salida($impabo);
                    if($salida){
                        $updinvoice = transfer::find($request->id);
                        $updinvoice->invoice = str_replace('"','',$salida);
                        $updinvoice->save();
                        $resinvoice = $updinvoice->fresh()->toArray();
                        if($resinvoice){
                                return response()->json($resinvoice,200);
                        }
                    }else{
                        return response()->json("No se realizo la salida",404);
                    }
        }else{
            return response()->json('No Existe la devolucion',404);
        }

    }

    public function nent(Request $request){
        $from = Stores::find($request->_store_from);
        $to = Stores::find($request->_store_to);
        $devolucion = $this->busDev($from->id,$request->refund);
        if($devolucion){
            $impabo = [
                "referencia"=>"FAC ".$request->invoice,
                "cliente"=>$from->_client,
                "observacion"=>'Traspaso a '.$to->alias,
                "total"=>$devolucion['total'],
                "products"=>$devolucion['productos']
            ];
            $entrada = $this->entry($to->id,$impabo);
            if($entrada){
                $updentry = transfer::find($request->id);
                $updentry->entry = str_replace('"','',$entrada);
                $updentry->save();
                $reenrada = $updentry->fresh()->toArray();
                return response()->json($reenrada,200);
            }else{
                return response()->json("No se pudo realizar la entrada",404);
            }
        }else{
            return response()->json('No Existe la devolucion',404);
        }

    }
}
