<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use App\Models\Solicitudes;
use App\Models\Stores;
use App\Models\ReplyClient;
use App\Models\Flight;
use Illuminate\Support\Facades\Http;

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
        $number = "5539297483";
        // $number = "5573461022";

        $data = file_get_contents($archivo);
        $file = base64_encode($data);

        $params=array(
            'token' => '7lxqd2rwots9u4lv',
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
        $workpoints = DB::table('stores')->get();
        $agent = DB::table('staff')->get();

        $res = [
            "branches"=>$workpoints,
            "agents"=>$agent
        ];

        if($res){
            return response()->json($res,200);
        }else{
            return response()->json("No hay error",501);
        }
    }

    public function Create(Request $request){
        $todo = $request->all();

        $ins = [
            "nom_cli"=>$todo['name'],
            // "address"=>json_encode($todo['address']),
            "celphone"=>$todo['phone'],
            "email"=>$todo['email'],
            "tickets"=>$todo['ticket'],
            "_store"=>$todo['branch']['id'],
            "price"=>$todo['priceList']['id'],
            "notes"=>$todo['notes'],
            "_status"=>0,
            "street"=>$todo['address']['street'],
            "num_int"=>$todo['address']['numint'],
            "num_ext"=>$todo['address']['numext'],
            "col"=>$todo['address']['colinia'],
            "mun"=>$todo['address']['mun'],
            "estado"=>$todo['address']['state'],
            "cp"=>$todo['address']['cp']
        ];

        // return response()->json($ins,200);
        $insert = DB::table('forms')->insertGetId($ins);
        if($insert){
            $data = ["mssg"=>"El formulario fue enviado correctamente","ID"=>$insert];
            return response()->json($data,200);
        }else{
            $data = ["mssg"=>"No se pudo enviar el formulario"];
            return response()->json($data,404);
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
            // $ip = $cedis->ip_address;
            $ip = '192.168.10.232:1619';
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
                return response()->json($res,201);
            }
        }else{return response()->json("no existe el id",404);
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
            $stores = Stores::WhereNotIn('id',[1,2])->get();
            foreach($solicitudes as $solicitud){
                $wrk = [];
                foreach($stores as $store){
                    // $ip = $store->ip_address;
                    $ip = '192.168.10.232:1619';
                    $inscli = Http::post($ip.'/storetools/public/api/Resources/createClientSuc',$solicitud);
                    $status = $inscli->status();
                    if($status == 201){
                        $wrk[] = $store->alias;
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
        // $ip = '192.168.10.232:1619';
        $getdev = Http::get($ip.'/storetools/public/api/Resources/getdev');
        return $getdev;
    }
}
