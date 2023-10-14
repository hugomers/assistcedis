<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

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
            "address"=>json_encode($todo['address']),
            "celphone"=>$todo['phone'],
            "email"=>$todo['email'],
            "tickets"=>$todo['ticket'],
            "_store"=>$todo['branch']['id'],
            "price"=>$todo['priceList']['id'],
            "notes"=>$todo['notes'],
            "_status"=>0
        ];

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
        $solicitudes = DB::table('forms AS F')->join('stores AS S','S.id','F._store')->select('F.*','S.name AS sucursal')->get();

        $res = [
            "solicitudes"=>$solicitudes,
        ];

        if($res){
            return response()->json($res,200);
        }else{
            return response()->json("No hay error",501);
        }
    }

}
