<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class MondayController extends Controller
{
    function __construct(){
        date_default_timezone_set(date_default_timezone_get());
    }
    public function apimon($query){
        $token = env('TOKEN_MA');//token monday
        $apiUrl = 'https://api.monday.com/v2';//conexion api monday
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];//capeceras monday
        $data = @file_get_contents($apiUrl, false, stream_context_create([
            'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode(['query' => $query]),
            ]
        ]));//conexion api monday
        return $style = json_decode($data,true);//se decodifica lo que se recibe
    }

    public function sendocument($archivo,$namefile,$number){
        $data = file_get_contents($archivo);
        $file = base64_encode($data);

        $params=array(
            'token' => '7lxqd2rwots9u4lv',
            'to' => $number,
            'filename' => $namefile,
            'document' => $file,
            'caption' => 'Se envia el reporte de checklist :)'
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

    /* INICIO METODOS CHECK LIST INICIO DE OPERACIONES */
    public function cheklistiop(Request $request){
        $pdf = [];
        $mysql = [];
        $idmon = $request->id;
        // $query = 'query {
        //     items_by_column_values (board_id: 4738541896, column_id: "estado", column_value: "Sin Enviar") {
        //         id
        //         column_values{
        //             id
        //             title
        //             text
        //         }
        //     }
        // }';//se genera consulta graphql para api de monday
        $query = 'query {
            items (ids: ['.$idmon.']) {
                id,
                column_values{
                    id
                    title
                    text
                }
          }
        }';//se genera consulta graphql para api de monday
        $monval = $this->apimon($query);
        $data = $monval['data']['items'];// se envia a mysql
        $mysql = $this->mysqliop($data);
        $pdfque = 'query {
            items (ids: ['.$idmon.']) {
                id,
                column_values{
                    id
                    title
                    text
                }
          }
        }';//se genera consulta graphql para api de monday
        $pdfval = $this->apimon($pdfque);
        $pdfdata = $pdfval['data']['items'];// se envia a pdf
        $pdf = $this->pdfiop($pdfdata);
        $res = [
            "pdf"=>$pdf,
            "mysql"=>$mysql
        ];

        return $res;
    }

    public function cheklistiopmas(){
        $pdf = [];
        $mysql = [];
        $query = 'query {
            items_by_column_values (board_id: 4738541896, column_id: "estado", column_value: "Sin Enviar") {
                id
                column_values{
                    id
                    title
                    text
                }
            }
        }';//se genera consulta graphql para api de monday
        // $query = 'query {
        //     items (ids: ['.$idmon.']) {
        //         id,
        //         column_values{
        //             id
        //             title
        //             text
        //         }
        //   }
        // }';//se genera consulta graphql para api de monday
        $monval = $this->apimon($query);
        $data = $monval['data']['items_by_column_values'];// se envia a mysql
        $mysql = $this->mysqliop($data);
        $pdfque = 'query {
            items_by_column_values (board_id: 4738541896, column_id: "estado9", column_value: "Sin Enviar") {
                id
                column_values{
                    id
                    title
                    text
                }
            }
        }';//se genera consulta graphql para api de monday
        $pdfval = $this->apimon($pdfque);
        $pdfdata = $pdfval['data']['items_by_column_values'];// se envia a pdf
        $pdf = $this->pdfiop($pdfdata);
        $res = [
            "pdf"=>$pdf,
            "mysql"=>$mysql
        ];

        return $res;
    }


    public function pdfiop($data){
        $res = [];
        $items = $data;
        foreach($items as $item){
            $ids = $item['id'];
            $tot1 = $item['column_values'][4]['text'] == "CUMPLE" ? 10 : 0;
            $tot2 = $item['column_values'][7]['text'] == "CUMPLE" ? 10 : 0;
            $tot3 = $item['column_values'][10]['text'] == "CUMPLE" ? 10 : 0;
            $tot4 = $item['column_values'][14]['text'] == "CUMPLE" ? 10 : 0;
            $tot5 = $item['column_values'][18]['text'] == "CUMPLE" ? 10 : 0;
            $tot6 = $item['column_values'][22]['text'] == "CUMPLE" ? 10 : 0;
            $tot7 = $item['column_values'][26]['text'] == "CUMPLE" ? 10 : 0;
            $tot8 = $item['column_values'][30]['text'] == "CUMPLE" ? 10 : 0;
            $tot9 = $item['column_values'][34]['text'] == "CUMPLE" ? 10 : 0;
            $tot10 = $item['column_values'][38]['text'] == "CUMPLE" ? 10 : 0;
            $calif = $tot1 + $tot2 + $tot3 + $tot4 + $tot5 + $tot6 + $tot7 + $tot8 + $tot9 + $tot10;

            $fecha = date('Y-m-d H_i_s', strtotime($item['column_values'][0]['text']) - 3600);

            $creapd = [
                "fecha"=>date('Y-m-d H:i:s', strtotime($item['column_values'][1]['text']) - 3600),
                "admin" => $item['column_values'][3]['text'],
                "sucursal" => $item['column_values'][2]['text'],
                "puntuacion" => $calif." / 100 ",
                "total" => $calif." / 100 ",
                "ppg1" => $item['column_values'][4]['title'],
                "tot1" => $item['column_values'][4]['text'],
                "obs1" => $item['column_values'][5]['text'],
                "pt1" => "",
                "per1" => $item['column_values'][6]['text'],
                "ppg2" => $item['column_values'][7]['title'],
                "tot2" => $item['column_values'][7]['text'],
                "obs2" => $item['column_values'][8]['text'],
                "pt2" => "",
                "per2" => $item['column_values'][6]['text'],
                "ppg3" => $item['column_values'][10]['title'],
                "tot3" => $item['column_values'][10]['text'],
                "obs3" => $item['column_values'][11]['text'],
                "pt3" => $item['column_values'][13]['text'],
                "per3" => $item['column_values'][12]['text'],
                "ppg4" => $item['column_values'][14]['title'],
                "tot4" => $item['column_values'][14]['text'],
                "obs4" => $item['column_values'][15]['text'],
                "pt4" => $item['column_values'][17]['text'],
                "per4" => $item['column_values'][16]['text'],
                "ppg5" => $item['column_values'][18]['title'],
                "tot5" => $item['column_values'][18]['text'],
                "obs5" => $item['column_values'][19]['text'],
                "pt5" => $item['column_values'][21]['text'],
                "per5" => $item['column_values'][20]['text'],
                "ppg6" => $item['column_values'][22]['title'],
                "tot6" => $item['column_values'][22]['text'],
                "obs6" => $item['column_values'][23]['text'],
                "pt6" => $item['column_values'][25]['text'],
                "per6" => $item['column_values'][24]['text'],
                "ppg7" => $item['column_values'][24]['title'],
                "tot7" => $item['column_values'][26]['text'],
                "obs7" => $item['column_values'][27]['text'],
                "pt7" => $item['column_values'][29]['text'],
                "per7" => $item['column_values'][28]['text'],
                "ppg8" => $item['column_values'][30]['title'],
                "tot8" => $item['column_values'][30]['text'],
                "obs8" => $item['column_values'][31]['text'],
                "pt8" => $item['column_values'][33]['text'],
                "per8" => $item['column_values'][32]['text'],
                "ppg9" => $item['column_values'][34]['title'],
                "tot9" => $item['column_values'][34]['text'],
                "obs9" => $item['column_values'][35]['text'],
                "pt9" => $item['column_values'][37]['text'],
                "per9" => $item['column_values'][36]['text'],
                "ppg10" => $item['column_values'][38]['title'],
                "tot10" => $item['column_values'][38]['text'],
                "obs10" => $item['column_values'][39]['text'],
                "pt10" => $item['column_values'][41]['text'],
                "per10" => $item['column_values'][40]['text'],
            ];
            $pdf = View::make('testPDF', $creapd)->render();

            $sucursal = $item['column_values'][2]['text'];

            switch ($sucursal) {
                case "SAN PABLO 1":
                $carpaud = "C:\REPORTESCHKL\SANPABLO1";
                $number = "+525534507385";
                break;
                case "SAN PABLO 2":
                $carpaud = "C:\REPORTESCHKL\SANPABLO2";
                $number = "+525537148456";
                break;
                case "SAN PABLO 3":
                $carpaud = "C:\REPORTESCHKL\SANPABLO3";
                $number = "+525532605854";
                break;
                    case "SAN PABLO C":
                $carpaud = "C:\REPORTESCHKL\SANPABLOC";
                $number = "+525535538498";
                break;
                    case "SOTANO":
                $carpaud = "C:\REPORTESCHKL\SOTANO";
                $number = "+525543918004";
                break;
                    case "CORREO 1":
                $carpaud = "C:\REPORTESCHKL\CORREO1";
                $number = "+525539945073";
                break;
                    case "CORREO 2":
                $carpaud = "C:\REPORTESCHKL\CORREO2";
                $number = "+525559024985";
                break;
                    case "RAMON CORONA 1":
                $carpaud = "C:\REPORTESCHKL\RAMONC1";
                $number = "+525554699569";
                break;
                    case "RAMON CORONA 2":
                $carpaud = "C:\REPORTESCHKL\RAMONC2";
                $number = "+525554699569";
                break;
                    case "BOLIVIA":
                $carpaud = "C:\REPORTESCHKL\BOLIVIA";
                $number = "+525540139765";
                break;
                    case "BRASIL 1":
                $carpaud = "C:\REPORTESCHKL\BRASIL1";
                $number = "+525539063473";
                break;
                    case "BRASIL 2":
                $carpaud = "C:\REPORTESCHKL\BRASIL2";
                $number = "+525543956395";
                break;
                    case "BRASIL 3":
                $carpaud = "C:\REPORTESCHKL\BRASIL3";
                $number = "+525537045056";
                // $number = "+525573461022";
                break;
                    case "APARTADO 1":
                $carpaud = "C:\REPORTESCHKL\APARTADO1";
                $number = "+525539110690";
                break;
                    case "APARTADO 2":
                $carpaud = "C:\REPORTESCHKL\APARTADO2";
                $number = "+525561557873";
                break;
                    case "PUEBLA":
                $carpaud = "C:\REPORTESCHKL\PUEBLA";
                $number = "+525541282698";
                break;
            }

            $options = new Options();
            $options->set('isRemoteEnabled',true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdf);
            $dompdf->render();
            $output = $dompdf->output();
            $namefile = 'checklistINIOP'.$fecha.$sucursal.'.pdf';
            $rutacompleta = $carpaud.'/'.$namefile;
            file_put_contents($rutacompleta,$output);
            $filename = $this->sendocument($rutacompleta,$namefile,$number);
            if($filename == "enviado"){
                $res [] = [
                    "msg"=>"enviado",
                    "archivo"=>$rutacompleta
                ];
                $chagemsg = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4738541896, column_values: "{\"estado9\" : {\"label\" : \"Enviado\"}}") {id}}';
                $chanms = $this->apimon($chagemsg);
            }else{
                $res [] = [
                    "msg"=>"no enviado",
                    "archivo"=>$rutacompleta
                ];
            }
        }
        return $res;
    }

    public function mysqliop($data){
        $goals=[];
        $fails=[];
        $items = $data;
        foreach($items as $item){
            $tot1 = $item['column_values'][4]['text'] == "CUMPLE" ? 10 : 0;
            $tot2 = $item['column_values'][7]['text'] == "CUMPLE" ? 10 : 0;
            $tot3 = $item['column_values'][10]['text'] == "CUMPLE" ? 10 : 0;
            $tot4 = $item['column_values'][14]['text'] == "CUMPLE" ? 10 : 0;
            $tot5 = $item['column_values'][18]['text'] == "CUMPLE" ? 10 : 0;
            $tot6 = $item['column_values'][22]['text'] == "CUMPLE" ? 10 : 0;
            $tot7 = $item['column_values'][26]['text'] == "CUMPLE" ? 10 : 0;
            $tot8 = $item['column_values'][30]['text'] == "CUMPLE" ? 10 : 0;
            $tot9 = $item['column_values'][34]['text'] == "CUMPLE" ? 10 : 0;
            $tot10 = $item['column_values'][38]['text'] == "CUMPLE" ? 10 : 0;

            $store = DB::table('stores')->where('name',$item['column_values'][2]['text'])->value('id');
            $idadmon = DB::table('staff')->where('complete_name',$item['column_values'][3]['text'])->value('id');
            $calif = $tot1 + $tot2 + $tot3 + $tot4 + $tot5 + $tot6 + $tot7 + $tot8 + $tot9 + $tot10;
            $fecha =date('Y-m-d H:i:s', strtotime($item['column_values'][1]['text']) - 3600);
            $ids = $item['id'];
            $id_mons = DB::table('checklistiop')->where('id_mon',$ids)->first();
            if($id_mons){

            }else{
                $evidence = json_encode($item['column_values'][42]['text']);
                $quiz = json_encode([
                    "pregunta1" => $item['column_values'][4]['title'],
                    "respuesta1" => $item['column_values'][4]['text'],
                    "observaciones1" => $item['column_values'][5]['text'],
                    "punto1" => "",
                    "colaborador1" => $item['column_values'][6]['text'],
                    "pregunta2" => $item['column_values'][7]['title'],
                    "respuesta2" => $item['column_values'][7]['text'],
                    "observaciones2" => $item['column_values'][8]['text'],
                    "punto2" => "",
                    "colaborador2"=> $item['column_values'][6]['text'],
                    "pregunta3" => $item['column_values'][10]['title'],
                    "respuesta3" => $item['column_values'][10]['text'],
                    "observaciones3" => $item['column_values'][11]['text'],
                    "punto3" => $item['column_values'][13]['text'],
                    "colaborador3"=> $item['column_values'][12]['text'],
                    "pregunta4" => $item['column_values'][14]['title'],
                    "respuesta4" => $item['column_values'][14]['text'],
                    "observaciones4" => $item['column_values'][15]['text'],
                    "punto4" => $item['column_values'][17]['text'],
                    "colaborador4"=> $item['column_values'][16]['text'],
                    "pregunta5" => $item['column_values'][18]['title'],
                    "respuesta5" => $item['column_values'][18]['text'],
                    "observaciones5" => $item['column_values'][19]['text'],
                    "punto5" => $item['column_values'][21]['text'],
                    "colaborador5"=> $item['column_values'][20]['text'],
                    "pregunta6" => $item['column_values'][22]['title'],
                    "respuesta6" => $item['column_values'][22]['text'],
                    "observaciones6" => $item['column_values'][23]['text'],
                    "punto6" => $item['column_values'][25]['text'],
                    "colaborador6"=> $item['column_values'][24]['text'],
                    "pregunta7" => $item['column_values'][26]['title'],
                    "respuesta7" => $item['column_values'][26]['text'],
                    "observaciones7" => $item['column_values'][27]['text'],
                    "punto7" => $item['column_values'][29]['text'],
                    "colaborador7"=> $item['column_values'][28]['text'],
                    "pregunta8" => $item['column_values'][30]['title'],
                    "respuesta8" => $item['column_values'][30]['text'],
                    "observaciones8" => $item['column_values'][31]['text'],
                    "punto8" => $item['column_values'][33]['text'],
                    "colaborador8"=> $item['column_values'][32]['text'],
                    "pregunta9" => $item['column_values'][34]['title'],
                    "respuesta9" => $item['column_values'][34]['text'],
                    "observaciones9" => $item['column_values'][35]['text'],
                    "punto9" => $item['column_values'][37]['text'],
                    "colaborador9"=> $item['column_values'][36]['text'],
                    "pregunta10" => $item['column_values'][38]['title'],
                    "respuesta10" => $item['column_values'][38]['text'],
                    "observaciones10" => $item['column_values'][39]['text'],
                    "punto10" => $item['column_values'][41]['text'],
                    "colaborador10" => $item['column_values'][40]['text'],
                ]);
                $inse = [
                    "_store"=>$store,
                    "created"=>$fecha,
                    "_admon"=>$idadmon,
                    "quiz"=>$quiz,
                    "evidence"=>$evidence,
                    "id_mon"=>$ids,
                    "calification"=>$calif
                ];
                $insert = DB::table('checklistiop')->insert($inse);
                if($insert){
                    $changtext = 'mutation {change_simple_column_value (item_id:'.$ids.', board_id:4738541896, column_id:"texto", value: "'.$calif."/"."100".'") {id}}';
                    $chagesta = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4738541896, column_values: "{\"estado\" : {\"label\" : \"Enviado\"}}") {id}}';
                    $chan = $this->apimon($changtext);
                    $chans = $this->apimon($chagesta);
                    $goals[] = "id ".$ids." insertado";
                }else{
                    $fails[]= "id ".$ids." no se inserto";
                }
            }
        }
        $res = [
            "goals"=>$goals,
            "fails"=>$fails
        ];
        return $res;
    }
    /*FINAL DE METODOS CHECK LIST INICIO DE OPERACIONES */

    /* INICIO METODOS CHECK LIST FINAL DE OPERACIONES */
    public function cheklistfinop(Request $request){
        $pdf = [];
        $mysql = [];
        $idmon = $request->id;
        $query = 'query {
            items (ids: ['.$idmon.']) {
                id,
                column_values{
                    id
                    title
                    text
                }
          }
        }';//se genera consulta graphql para api de monday
        $monval = $this->apimon($query);
        $data = $monval['data']['items'];// se envia a mysql
        $mysql = $this->mysqlfinop($data);

        $pdfque = 'query {
            items (ids: ['.$idmon.']) {
                id,
                column_values{
                    id
                    title
                    text
                }
          }
        }';//se genera consulta graphql para api de monday
        $pdfval = $this->apimon($pdfque);
        $pdfdata = $pdfval['data']['items'];// se envia a pdf
        $pdf = $this->pdffinop($pdfdata);

        $res = [
            "pdf"=>$pdf,
            "mysql"=>$mysql
        ];

        return $res;
    }

    public function cheklistfinopmas(){
        $pdf = [];
        $mysql = [];
        $query = 'query {
            items_by_column_values (board_id: 4738652916, column_id: "estado", column_value: "Sin Enviar") {
                id
                column_values{
                    id
                    title
                    text
                }
            }
        }';//se genera consulta graphql para api de monday
        $monval = $this->apimon($query);
        $data = $monval['data']['items_by_column_values'];// se envia a mysql
        $mysql = $this->mysqlfinop($data);
        $pdfque = 'query {
            items_by_column_values (board_id: 4738652916, column_id: "estado7", column_value: "Sin Enviar") {
                id
                column_values{
                    id
                    title
                    text
                }
            }
        }';//se genera consulta graphql para api de monday
        $pdfval = $this->apimon($pdfque);
        $pdfdata = $pdfval['data']['items_by_column_values'];// se envia a pdf
        $pdf = $this->pdffinop($pdfdata);
        $res = [
            "pdf"=>$pdf,
            "mysql"=>$mysql
        ];

        return $res;
    }

    public function pdffinop($data){
        $items = $data;
        foreach($items as $item){
            $ids = $item['id'];
            $tot1 = $item['column_values'][4]['text'] == "CUMPLE" ? 10 : 0;
            $tot2 = $item['column_values'][8]['text'] == "CUMPLE" ? 10 : 0;
            $tot3 = $item['column_values'][12]['text'] == "CUMPLE" ? 5 : 0;
            $tot4 = $item['column_values'][16]['text'] == "CUMPLE" ? 10 : 0;
            $tot5 = $item['column_values'][20]['text'] == "CUMPLE" ? 10 : 0;
            $tot6 = $item['column_values'][23]['text'] == "CUMPLE" ? 5 : 0;
            $tot7 = $item['column_values'][27]['text'] == "CUMPLE" ? 10 : 0;
            $tot8 = $item['column_values'][31]['text'] == "CUMPLE" ? 10 : 0;
            $tot9 = $item['column_values'][35]['text'] == "CUMPLE" ? 10 : 0;
            $tot10 = $item['column_values'][39]['text'] == "CUMPLE" ? 5 : 0;
            $tot11 = $item['column_values'][43]['text'] == "CUMPLE" ? 5 : 0;
            $tot12 = $item['column_values'][47]['text'] == "CUMPLE" ? 10 : 0;
            $calif = $tot1 + $tot2 + $tot3 + $tot4 + $tot5 + $tot6 + $tot7 + $tot8 + $tot9 + $tot10 + $tot11 + $tot12;

            $fecha = date('Y-m-d H_i_s', strtotime($item['column_values'][1]['text']) - 3600);

            $creapd = [
                "fecha"=>date('Y-m-d H:i:s', strtotime($item['column_values'][1]['text']) - 3600),
                "admin" => $item['column_values'][3]['text'],
                "sucursal" => $item['column_values'][2]['text'],
                "puntuacion" => $calif." / 100 ",
                "total" => $calif." / 100 ",
                "ppg1" => $item['column_values'][4]['title'],
                "tot1" => $item['column_values'][4]['text'],
                "obs1" => $item['column_values'][5]['text'],
                "pt1" =>  $item['column_values'][7]['text'],
                "per1" => $item['column_values'][6]['text'],
                "ppg2" => $item['column_values'][8]['title'],
                "tot2" => $item['column_values'][8]['text'],
                "obs2" => $item['column_values'][9]['text'],
                "pt2" =>  $item['column_values'][11]['text'],
                "per2" => $item['column_values'][10]['text'],
                "ppg3" => $item['column_values'][12]['title'],
                "tot3" => $item['column_values'][12]['text'],
                "obs3" => $item['column_values'][13]['text'],
                "pt3" =>  $item['column_values'][15]['text'],
                "per3" => $item['column_values'][14]['text'],
                "ppg4" => $item['column_values'][16]['title'],
                "tot4" => $item['column_values'][16]['text'],
                "obs4" => $item['column_values'][17]['text'],
                "pt4" =>  $item['column_values'][19]['text'],
                "per4" => $item['column_values'][18]['text'],
                "ppg5" => $item['column_values'][20]['title'],
                "tot5" => $item['column_values'][20]['text'],
                "obs5" => $item['column_values'][21]['text'],
                "pt5" =>  "",
                "per5" => $item['column_values'][22]['text'],
                "ppg6" => $item['column_values'][23]['title'],
                "tot6" => $item['column_values'][23]['text'],
                "obs6" => $item['column_values'][24]['text'],
                "pt6" =>  $item['column_values'][26]['text'],
                "per6" => $item['column_values'][25]['text'],
                "ppg7" => $item['column_values'][27]['title'],
                "tot7" => $item['column_values'][27]['text'],
                "obs7" => $item['column_values'][28]['text'],
                "pt7" =>  $item['column_values'][30]['text'],
                "per7" => $item['column_values'][29]['text'],
                "ppg8" => $item['column_values'][31]['title'],
                "tot8" => $item['column_values'][31]['text'],
                "obs8" => $item['column_values'][32]['text'],
                "pt8" =>  $item['column_values'][34]['text'],
                "per8" => $item['column_values'][33]['text'],
                "ppg9" => $item['column_values'][35]['title'],
                "tot9" => $item['column_values'][35]['text'],
                "obs9" => $item['column_values'][36]['text'],
                "pt9" =>  $item['column_values'][38]['text'],
                "per9" => $item['column_values'][37]['text'],
                "ppg10" =>$item['column_values'][39]['title'],
                "tot10" =>$item['column_values'][39]['text'],
                "obs10" =>$item['column_values'][40]['text'],
                "pt10" => $item['column_values'][42]['text'],
                "per10" =>$item['column_values'][41]['text'],
                "ppg11" =>$item['column_values'][43]['title'],
                "tot11" =>$item['column_values'][43]['text'],
                "obs11" =>$item['column_values'][44]['text'],
                "pt11" => $item['column_values'][46]['text'],
                "per11" =>$item['column_values'][45]['text'],
                "ppg12" =>$item['column_values'][47]['title'],
                "tot12" =>$item['column_values'][47]['text'],
                "obs12" =>$item['column_values'][48]['text'],
                "pt12" => $item['column_values'][50]['text'],
                "per12" =>$item['column_values'][49]['text'],
            ];
            $pdf = View::make('finop', $creapd)->render();

            $sucursal = $item['column_values'][2]['text'];

            switch ($sucursal) {
                case "SAN PABLO 1":
                $carpaud = "C:\REPORTESCHKL\SANPABLO1";
                $number = "+525534507385";
                break;
                case "SAN PABLO 2":
                $carpaud = "C:\REPORTESCHKL\SANPABLO2";
                $number = "+525537148456";
                break;
                case "SAN PABLO 3":
                $carpaud = "C:\REPORTESCHKL\SANPABLO3";
                $number = "+525532605854";
                break;
                    case "SAN PABLO C":
                $carpaud = "C:\REPORTESCHKL\SANPABLOC";
                $number = "+525535538498";
                break;
                    case "SOTANO":
                $carpaud = "C:\REPORTESCHKL\SOTANO";
                $number = "+525543918004";
                break;
                    case "CORREO 1":
                $carpaud = "C:\REPORTESCHKL\CORREO1";
                $number = "+525539945073";
                break;
                    case "CORREO 2":
                $carpaud = "C:\REPORTESCHKL\CORREO2";
                $number = "+525559024985";
                break;
                    case "RAMON CORONA 1":
                $carpaud = "C:\REPORTESCHKL\RAMONC1";
                $number = "+525554699569";
                break;
                    case "RAMON CORONA 2":
                $carpaud = "C:\REPORTESCHKL\RAMONC2";
                $number = "+525554699569";
                break;
                    case "BOLIVIA":
                $carpaud = "C:\REPORTESCHKL\BOLIVIA";
                $number = "+525540139765";
                break;
                    case "BRASIL 1":
                $carpaud = "C:\REPORTESCHKL\BRASIL1";
                $number = "+525539063473";
                break;
                    case "BRASIL 2":
                $carpaud = "C:\REPORTESCHKL\BRASIL2";
                $number = "+525543956395";
                break;
                    case "BRASIL 3":
                $carpaud = "C:\REPORTESCHKL\BRASIL3";
                $number = "+525537045056";
                // $number = "+525573461022";
                break;
                    case "APARTADO 1":
                $carpaud = "C:\REPORTESCHKL\APARTADO1";
                $number = "+525539110690";
                break;
                    case "APARTADO 2":
                $carpaud = "C:\REPORTESCHKL\APARTADO2";
                $number = "+525561557873";
                break;
                    case "PUEBLA":
                $carpaud = "C:\REPORTESCHKL\PUEBLA";
                $number = "+525541282698";
                break;
            }

            $options = new Options();
            $options->set('isRemoteEnabled',true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdf);
            $dompdf->render();
            $output = $dompdf->output();
            $namefile = 'checklistFINOP'.$fecha.$sucursal.'.pdf';
            $rutacompleta = $carpaud.'/'.$namefile;
            file_put_contents($rutacompleta,$output);
            $filename = $this->sendocument($rutacompleta,$namefile,$number);
            if($filename == "enviado"){
                $res [] = [
                    "msg"=>"enviado",
                    "archivo"=>$rutacompleta
                ];
                $chagemsg = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4738652916, column_values: "{\"estado7\" : {\"label\" : \"Enviado\"}}") {id}}';
                $chanms = $this->apimon($chagemsg);
            }else{
                $res [] = [
                    "msg"=>"no enviado",
                    "archivo"=>$rutacompleta
                ];
            }
        }
        return $res;
    }

    public function mysqlfinop($data){
        $goals=[];
        $fails=[];
        $items = $data;
        foreach($items as $item){
            $tot1 = $item['column_values'][4]['text'] == "CUMPLE" ? 10 : 0;
            $tot2 = $item['column_values'][8]['text'] == "CUMPLE" ? 10 : 0;
            $tot3 = $item['column_values'][12]['text'] == "CUMPLE" ? 5 : 0;
            $tot4 = $item['column_values'][16]['text'] == "CUMPLE" ? 10 : 0;
            $tot5 = $item['column_values'][20]['text'] == "CUMPLE" ? 10 : 0;
            $tot6 = $item['column_values'][23]['text'] == "CUMPLE" ? 5 : 0;
            $tot7 = $item['column_values'][27]['text'] == "CUMPLE" ? 10 : 0;
            $tot8 = $item['column_values'][31]['text'] == "CUMPLE" ? 10 : 0;
            $tot9 = $item['column_values'][35]['text'] == "CUMPLE" ? 10 : 0;
            $tot10 = $item['column_values'][39]['text'] == "CUMPLE" ? 5 : 0;
            $tot11 = $item['column_values'][43]['text'] == "CUMPLE" ? 5 : 0;
            $tot12 = $item['column_values'][47]['text'] == "CUMPLE" ? 10 : 0;


            $store = DB::table('stores')->where('name',$item['column_values'][2]['text'])->value('id');
            $idadmon = DB::table('staff')->where('complete_name',$item['column_values'][3]['text'])->value('id');
            $calif = $tot1 + $tot2 + $tot3 + $tot4 + $tot5 + $tot6 + $tot7 + $tot8 + $tot9 + $tot10 + $tot11 + $tot12;
            $fecha =date('Y-m-d H:i:s', strtotime($item['column_values'][1]['text']) - 3600);
            $ids = $item['id'];
            $id_mons = DB::table('checklistfinop')->where('id_mon',$ids)->first();
            if($id_mons){

            }else{
                $evidence = json_encode($item['column_values'][51]['text']);
                $quiz = json_encode([
                    "pregunta1" => $item['column_values'][4]['title'],
                    "respuesta1" => $item['column_values'][4]['text'],
                    "observaciones1" => $item['column_values'][5]['text'],
                    "punto1"  =>  $item['column_values'][7]['text'],
                    "colaborador1" => $item['column_values'][6]['text'],
                    "pregunta2" => $item['column_values'][8]['title'],
                    "respuesta2" => $item['column_values'][8]['text'],
                    "observaciones2" => $item['column_values'][9]['text'],
                    "punto2"  =>  $item['column_values'][11]['text'],
                    "colaborador2" => $item['column_values'][10]['text'],
                    "pregunta3" => $item['column_values'][12]['title'],
                    "respuesta3" => $item['column_values'][12]['text'],
                    "observaciones3" => $item['column_values'][13]['text'],
                    "punto3"  =>  $item['column_values'][15]['text'],
                    "colaborador3" => $item['column_values'][14]['text'],
                    "pregunta4" => $item['column_values'][16]['title'],
                    "respuesta4" => $item['column_values'][16]['text'],
                    "observaciones4" => $item['column_values'][16]['text'],
                    "punto4"  =>  $item['column_values'][19]['text'],
                    "colaborador4" => $item['column_values'][18]['text'],
                    "pregunta5" => $item['column_values'][20]['title'],
                    "respuesta5" => $item['column_values'][20]['text'],
                    "observaciones5" => $item['column_values'][21]['text'],
                    "punto5"  =>  "",
                    "colaborador5" => $item['column_values'][22]['text'],
                    "pregunta6" => $item['column_values'][23]['title'],
                    "respuesta6" => $item['column_values'][23]['text'],
                    "observaciones6" => $item['column_values'][24]['text'],
                    "punto6"  =>  $item['column_values'][26]['text'],
                    "colaborador6" => $item['column_values'][25]['text'],
                    "pregunta7" => $item['column_values'][27]['title'],
                    "respuesta7" => $item['column_values'][27]['text'],
                    "observaciones7" => $item['column_values'][29]['text'],
                    "punto7"  =>  $item['column_values'][30]['text'],
                    "colaborador7" => $item['column_values'][29]['text'],
                    "pregunta8" => $item['column_values'][31]['title'],
                    "respuesta8" => $item['column_values'][31]['text'],
                    "observaciones8" => $item['column_values'][32]['text'],
                    "punto8"  =>  $item['column_values'][34]['text'],
                    "colaborador8" => $item['column_values'][33]['text'],
                    "pregunta9" => $item['column_values'][35]['title'],
                    "respuesta9" => $item['column_values'][35]['text'],
                    "observaciones9" => $item['column_values'][36]['text'],
                    "punto9"  =>  $item['column_values'][38]['text'],
                    "colaborador9" => $item['column_values'][37]['text'],
                    "pregunta10"=>$item['column_values'][39]['title'],
                    "respuesta10"=>$item['column_values'][39]['text'],
                    "observaciones10"=>$item['column_values'][40]['text'],
                    "punto10" => $item['column_values'][42]['text'],
                    "colaborador10"=>$item['column_values'][41]['text'],
                    "pregunta11"=>$item['column_values'][43]['title'],
                    "respuesta11"=>$item['column_values'][43]['text'],
                    "observaciones11"=>$item['column_values'][44]['text'],
                    "punto11" => $item['column_values'][46]['text'],
                    "colaborador11"=>$item['column_values'][45]['text'],
                    "pregunta12"=>$item['column_values'][47]['title'],
                    "respuesta12"=>$item['column_values'][47]['text'],
                    "observaciones12"=>$item['column_values'][48]['text'],
                    "punto12" => $item['column_values'][50]['text'],
                    "colaborador12"=>$item['column_values'][49]['text'],
                ]);
                $inse = [
                    "_store"=>$store,
                    "created"=>$fecha,
                    "_admon"=>$idadmon,
                    "quiz"=>$quiz,
                    "evidence"=>$evidence,
                    "id_mon"=>$ids,
                    "calification"=>$calif
                ];
                $insert = DB::table('checklistfinop')->insert($inse);
                if($insert){
                    $changtext = 'mutation {change_simple_column_value (item_id:'.$ids.', board_id:4738652916, column_id:"texto", value: "'.$calif."/"."100".'") {id}}';
                    $chagesta = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4738652916, column_values: "{\"estado\" : {\"label\" : \"Enviado\"}}") {id}}';
                    $chan = $this->apimon($changtext);
                    $chans = $this->apimon($chagesta);
                    $goals[] = "id ".$ids." insertado";
                }else{
                    $fails[]= "id ".$ids." no se inserto";
                }
            }
        }
        $res = [
            "goals"=>$goals,
            "fails"=>$fails
        ];
        return $res;
    }
    /*FINAL DE METODOS CHECK LIST FINAL DE OPERACIONES */

    /* INICIO DE METODOS ACTUALIZAR PERSONAL DE LOS MENUS DESPLEGABLES DE FORMULARIOS (JUSTIFICACIONES, CHECKLIST INICIO, CHECKLIST FINAL)*/
    public function completestaff(){
        $colaboradores = [];
        $colab = 'query {
            items_by_column_values (board_id: 1520861792, column_id: "estatus", column_value: "ACTIVO") {
            name,
            column_values  {
                id
                title
                text
                }
            }
        }';//se genera query para buscar a los agentes
        $exist = $this->apimon($colab);//se executa
        $agentes = $exist['data']['items_by_column_values'];
        foreach($agentes as $agente){
            $colaboradores [] = [
                "nombre"=>$agente['name'],
                "id_rc"=>$agente['column_values'][0]['text'],
                "sucursal"=>$agente['column_values'][1]['text']
            ];
        }

        $iniop = $this->dropdowniop($colaboradores);
        $finop = $this->dropdownfinop($colaboradores);
        $just = $this->dropdownjust($colaboradores);
        $clim = $this->dropdownclima($colaboradores);
        $act = $this->dropdownactas($colaboradores);
        $san = $this->dropdownsanciones($colaboradores);
        $res = [
            "inicio operaciones"=>$iniop,
            "final operaciones"=>$finop,
            "justificaciones"=>$just,
            "clima_laboral"=>$clim,
            "actas_adm"=>$act,
            "sanciones"=>$san
        ];
        return $res;

    }

    public function dropdowniop($colaboradores){
        $notin = ["CEDIS","OFICINA","MANTENIMIENTO","TEXCOCO","AUDITORIA/INVENTARIOS"];
        $colabs = array_filter($colaboradores, function ($elemnts) use($notin) {
            return isset($elemnts['sucursal']) && !in_array($elemnts['sucursal'],$notin);
        });
        foreach($colabs as $colab){
            $agentes [] = $colab['nombre'];
        };
        asort($agentes);

        $todes = 'query {
            boards (ids: 4738541896) {
              columns {
                id
                title
                  }
              }
          }
          ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $results = array_filter($columnas, function ($element) {
            return isset($element['title']) && $element['title'] === 'QUIEN(ES) NO CUMPLE(N)';
        });//se buscan solo los quw son son quienes

        foreach($results as $result){
            $idcol = $result['id'];
            $query = 'mutation {
                change_simple_column_value (item_id:4738821251, board_id:4738541896, column_id:'.$idcol.', value: "jugitodenaranja") {
                    id
                }
            }';
            $monval  = $this->apimon($query);//se recibe
            $errmsg = $monval['error_message'];
            $errsub = substr($errmsg, strpos($errmsg,"{"));
            $errs = explode(",",$errsub);
            $val = [];
            foreach($errs as $err){
                $val [] = str_replace("}","",str_replace(": ","",substr($err,strpos($err,":"))));
            }
            $cols [] = [
                "id"=>$idcol, "values"=>$val];
        }

        foreach($cols as $column){
            $ids = $column['id'];
            asort($column['values']);
            $depen  = array_values($column['values']);
            $agnts = array_values($agentes);
            $out = array_values(array_diff($agnts,$depen));
            $inp = implode(",",$out);
            $inser = 'mutation {
                change_simple_column_value(item_id:4738821251, board_id:4738541896, column_id: '.$ids.', value: "'.$inp.'", create_labels_if_missing: true) {
                  id
                  }
           }';
           $dependientes = $this->apimon($inser);
           $res[] = ["idcolumn"=>$ids,"faltantes"=>$out];
        }
        return $res;
    }

    public function dropdownfinop($colaboradores){
        $notin = ["CEDIS","OFICINA","MANTENIMIENTO","TEXCOCO","AUDITORIA/INVENTARIOS"];
        $colabs = array_filter($colaboradores, function ($elemnts) use($notin) {
            return isset($elemnts['sucursal']) && !in_array($elemnts['sucursal'],$notin);
        });
        foreach($colabs as $colab){
            $agentes [] = $colab['nombre'];
        };
        asort($agentes);

        $todes = 'query {
            boards (ids: 4738652916) {
              columns {
                id
                title
                  }
              }
          }
          ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $results = array_filter($columnas, function ($element) {
            return isset($element['title']) && $element['title'] === 'QUIEN(ES) NO CUMPLE(N)';
        });//se buscan solo los quw son son quienes
        foreach($results as $result){
            $idcol = $result['id'];
            $query = 'mutation {
                change_simple_column_value (item_id:4738834746, board_id:4738652916, column_id:'.$idcol.', value: "jugitodenaranja") {
                    id
                }
            }';
            $monval  = $this->apimon($query);//se recibe
            $errmsg = $monval['error_message'];
            $errsub = substr($errmsg, strpos($errmsg,"{"));
            $errs = explode(",",$errsub);
            $val = [];
            foreach($errs as $err){
                $val [] = str_replace("}","",str_replace(": ","",substr($err,strpos($err,":"))));
            }
            $cols [] = [
                "id"=>$idcol, "values"=>$val];
        }

        foreach($cols as $column){
            $ids = $column['id'];
            asort($column['values']);
            $depen  = array_values($column['values']);
            $agnts = array_values($agentes);
            $out = array_values(array_diff($agnts,$depen));
            $inp = implode(",",$out);
            $inser = 'mutation {
                change_simple_column_value(item_id:4738834746, board_id:4738652916, column_id: '.$ids.', value: "'.$inp.'", create_labels_if_missing: true) {
                  id
                  }
           }';
           $dependientes = $this->apimon($inser);
           $res[] = ["idcolumn"=>$ids,"faltantes"=>$out];
        }
        return $res;
    }

    public function dropdownjust($colaboradores){
        //4767413950

        foreach($colaboradores as $colab){
            $agentes [] = $colab['nombre'];
        };
        asort($agentes);

        $todes = 'query {
            boards (ids: 4403681072) {
            columns {
                id
                title
                }
            }
        }
        ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $results = array_filter($columnas, function ($element) {
            return isset($element['title']) && $element['title'] === 'Persona';
        });//se buscan solo los quw son son quienes
        foreach($results as $result){
            $idcol = $result['id'];
            $query = 'mutation {
                change_simple_column_value (item_id:4767413950, board_id:4403681072, column_id:'.$idcol.', value: "jugitodenaranja") {
                    id
                }
            }';
            $monval  = $this->apimon($query);//se recibe
            $errmsg = $monval['error_message'];
            $errsub = substr($errmsg, strpos($errmsg,"{"));
            $errs = explode(",",$errsub);
            $val = [];
            foreach($errs as $err){
                $val [] = str_replace("}","",str_replace(": ","",substr($err,strpos($err,":"))));
            }
            $cols [] = [
                "id"=>$idcol, "values"=>$val];
        }

        foreach($cols as $column){
            $ids = $column['id'];
            asort($column['values']);
            $depen  = array_values($column['values']);
            $agnts = array_values($agentes);
            $out = array_values(array_diff($agnts,$depen));
            $inp = implode(",",$out);
            $inser = 'mutation {
                change_simple_column_value(item_id:4767413950, board_id:4403681072, column_id: '.$ids.', value: "'.$inp.'", create_labels_if_missing: true) {
                id
                }
        }';
        $dependientes = $this->apimon($inser);
        $res[] = ["idcolumn"=>$ids,"faltantes"=>$out];
        }
        return $res;

    }

    public function dropdownclima($colaboradores){
        foreach($colaboradores as $colab){
            $agentes [] = $colab['nombre'];
        };
        asort($agentes);

        $todes = 'query {
            boards (ids: 4813574060) {
            columns {
                id
                title
                }
            }
        }
        ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $arraynam = ["NOMBRE","JEFE DIRECTO","Si es mala indica el nombre con quien(es):","Si es muy a menudo indica el Nombre hacia quien(es):","Con quien(es)"];
        $results = array_filter($columnas, function ($element) use($arraynam){
            return isset($element['title']) && in_array($element['title'],$arraynam);
        });//se buscan solo los quw son son quienes
        foreach($results as $result){
            $idcol = $result['id'];
            $query = 'mutation {
                change_simple_column_value (item_id:4813863697, board_id:4813574060, column_id:'.$idcol.', value: "jugitodenaranja") {
                    id
                }
            }';
            $monval  = $this->apimon($query);//se recibe
            $errmsg = $monval['error_message'];
            $errsub = substr($errmsg, strpos($errmsg,"{"));
            $errs = explode(",",$errsub);
            $val = [];
            foreach($errs as $err){
                $val [] = str_replace("}","",str_replace(": ","",substr($err,strpos($err,":"))));
            }
            $cols [] = [
                "id"=>$idcol, "values"=>$val];
        }

        foreach($cols as $column){
            $ids = $column['id'];
            asort($column['values']);
            $depen  = array_values($column['values']);
            $agnts = array_values($agentes);
            $out = array_values(array_diff($agnts,$depen));
            $inp = implode(",",$out);
            $inser = 'mutation {
                change_simple_column_value(item_id:4813863697, board_id:4813574060, column_id: '.$ids.', value: "'.$inp.'", create_labels_if_missing: true) {
                id
                }
        }';
        $dependientes = $this->apimon($inser);
        $res[] = ["idcolumn"=>$ids,"faltantes"=>$out];
        }
        return $res;
    }

    public function dropdownactas($colaboradores){
        foreach($colaboradores as $colab){
            $agentes [] = $colab['nombre'];
        };
        asort($agentes);

        $todes = 'query {
            boards (ids: 4933901663) {
            columns {
                id
                title
                }
            }
        }
        ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $arraynam = ["COLABORADOR"];
        $results = array_filter($columnas, function ($element) use($arraynam){
            return isset($element['title']) && in_array($element['title'],$arraynam);
        });//se buscan solo los quw son son quienes
        foreach($results as $result){
            $idcol = $result['id'];
            $query = 'mutation {
                change_simple_column_value (item_id:4935905525, board_id:4933901663, column_id:'.$idcol.', value: "jugitodenaranja") {
                    id
                }
            }';
            $monval  = $this->apimon($query);//se recibe
            $errmsg = $monval['error_message'];
            $errsub = substr($errmsg, strpos($errmsg,"{"));
            $errs = explode(",",$errsub);
            $val = [];
            foreach($errs as $err){
                $val [] = str_replace("}","",str_replace(": ","",substr($err,strpos($err,":"))));
            }
            $cols [] = [
                "id"=>$idcol, "values"=>$val];
        }

        foreach($cols as $column){
            $ids = $column['id'];
            asort($column['values']);
            $depen  = array_values($column['values']);
            $agnts = array_values($agentes);
            $out = array_values(array_diff($agnts,$depen));
            $inp = implode(",",$out);
            $inser = 'mutation {
                change_simple_column_value(item_id:4935905525, board_id:4933901663, column_id: '.$ids.', value: "'.$inp.'", create_labels_if_missing: true) {
                id
                }
        }';
        $dependientes = $this->apimon($inser);
        $res[] = ["idcolumn"=>$ids,"faltantes"=>$out];
        }
        return $res;
    }

    public function dropdownsanciones($colaboradores){
        foreach($colaboradores as $colab){
            $agentes [] = $colab['nombre'];
        };
        asort($agentes);

        $todes = 'query {
            boards (ids: 4967819212) {
            columns {
                id
                title
                }
            }
        }
        ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $arraynam = ["QUIEN ES SANCIONADO"];
        $results = array_filter($columnas, function ($element) use($arraynam){
            return isset($element['title']) && in_array($element['title'],$arraynam);
        });//se buscan solo los quw son son quienes
        foreach($results as $result){
            $idcol = $result['id'];
            $query = 'mutation {
                change_simple_column_value (item_id:4968039187, board_id:4967819212, column_id:'.$idcol.', value: "jugitodenaranja") {
                    id
                }
            }';
            $monval  = $this->apimon($query);//se recibe
            $errmsg = $monval['error_message'];
            $errsub = substr($errmsg, strpos($errmsg,"{"));
            $errs = explode(",",$errsub);
            $val = [];
            foreach($errs as $err){
                $val [] = str_replace("}","",str_replace(": ","",substr($err,strpos($err,":"))));
            }
            $cols [] = [
                "id"=>$idcol, "values"=>$val];
        }

        foreach($cols as $column){
            $ids = $column['id'];
            asort($column['values']);
            $depen  = array_values($column['values']);
            $agnts = array_values($agentes);
            $out = array_values(array_diff($agnts,$depen));
            $inp = implode(",",$out);
            $inser = 'mutation {
                change_simple_column_value(item_id:4968039187, board_id:4967819212, column_id: '.$ids.', value: "'.$inp.'", create_labels_if_missing: true) {
                id
                }
        }';
        $dependientes = $this->apimon($inser);
        $res[] = ["idcolumn"=>$ids,"faltantes"=>$out];
        }
        return $res;
    }

    /* TERMINO DE  METODOS ACTUALIZAR PERSONAL DE LOS MENUS DESPLEGABLES DE FORMULARIOS (JUSTIFICACIONES, CHECKLIST INICIO, CHECKLIST FINAL)*/

    public function staff(){
        $users = DB::table('staff')->get();
        foreach($users as $user){
            $id = $user->complete_name;
            $ingres = $user->ingress;
            $clas = $user->clasification;

            $query = 'query {
                items_by_column_values (board_id: 1520861792, column_id: "estatus", column_value: "ACTIVO") {
                    id
                name
                }
            }';
            $mon = $this->apimon($query);
          return $mon;

        }
    }

    public function justification(){
        $query = 'query {
            items_by_column_values (board_id: 4403681072, column_id: "estado1", column_value: "Sin Enviar") {
                id
                column_values{
                    id
                    title
                    text
                }
            }
        }';//se genera consulta graphql para api de monday
        $monval = $this->apimon($query);
        $received = $monval['data']['items_by_column_values'];// se envia a mysql

        foreach($received as $new){
            $type = DB::table('justification_types')->where('name',$new['column_values'][3]['text'])->value('id');
            $user = DB::table('staff')->where('complete_name',$new['column_values'][2]['text'])->value('id');
            $id_mon = $new['id'];
            $created = date('Y-m-d H:i:s', strtotime($new['column_values'][1]['text']) - 3600);
            $inip = $new['column_values'][4]['text'];
            $finip = $new['column_values'][5]['text'];
            $percen = $new['column_values'][10]['text'];
            $notes = $new['column_values'][6]['text'];
            $autorized = $new['column_values'][11]['text'];

            $justi [] = [
                "_staff"=>$user,
                "created_at"=>$created,
                "start_date"=>$inip,
                "final_date"=>$finip,
                "_type"=>$type,
                "percentaje"=>$percen,
                "notes"=>$notes,
                "mid"=>$id_mon,
                "autorized"=>$autorized,
            ];
        }

        $data = array_filter($justi, function($element){
            return isset($element['autorized']) && $element['autorized'] === 'AUTORIZADO';
        });

        foreach($data as $dat){

            $nuevo = [
                "_staff"=>$dat['_staff'],
                "created_at"=>$dat['created_at'],
                "start_date"=>$dat['start_date'],
                "final_date"=>$dat['final_date'],
                "_type"=>$dat['_type'],
                "percentage"=>$dat['percentaje'],
                "notes"=>$dat['notes'],
                "mid"=>$dat['mid']
            ];
            $insert = DB::table('assist_justification')->insert($nuevo);
            if($insert){
               $change = 'mutation {
                change_simple_column_value (item_id:'.$dat['mid'].', board_id:4403681072, column_id:"estado1", value: "Enviado") {
                  id
                }
              }';
              $valchan = $this->apimon($change);
            }

        }

        return response()->json("Justificaciones Replicadas",200);
    }

    public function Cifras(){
        $query = 'query {
            boards (ids: 2275639723) {
                items {
                id
                name
                column_values  {
                    id
                    title
                    text
                }
                }
            }
            }';
        $mon = $this->apimon($query);
        return $mon;
    }

    public function findid(Request $request){
        $idtablero = $request->tablero;
        $query = 'query {
            boards (ids: '.$idtablero.') {
                items {
                id
                name
                column_values  {
                    id
                    title
                    text
                }
                }
            }
            }';
        $ids = $this->apimon($query);
        return response()->json($ids);
    }
}
