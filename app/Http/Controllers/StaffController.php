<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;

class StaffController extends Controller
{

    public function replystaff(){
        $upins = [//se guardan registros actualizados o insertados
            'inserts'=>[],
            'updates'=>[]
        ];
        $fail = [//contenedor para los fails jaja
            'inserts'=>[],
            'updates'=>[],
            'names'=>[],
            'sucursal'=>[]
        ];

        $token = env('TOKEN_MA');//token monday
        $apiUrl = 'https://api.monday.com/v2';//conexion api monday
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];//capeceras monday
        $query = 'query {
            items_page_by_column_values ( limit:500 board_id: 1520861792, columns: [{column_id: "estatus", column_values: ["ACTIVO"]}]) {
              cursor
              items {
                name
                column_values{
                  id
                  text
                  column{
                    title
                    }
                }
              }
            }
          }';
        $data = @file_get_contents($apiUrl, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode(['query' => $query]),
        ]
        ]));//conexion api monday
        $style = json_decode($data,true);//se decodifica lo que se recibe
        $row = $style['data']['items_page_by_column_values']['items'];//recorremos arreglos hasta los que ocuparemos

        $usersmon = array_map(function($val){
            $suc = $val['column_values'][1]['text'];
            $activ = $val['column_values'][3]['text'] == "ACTIVO" ? 1 : 0;
            $stor =  $suc == "OFICINA" || $suc == "MANTENIMIENTO" || $suc == "AUDITORIA/INVENTARIOS" ? "CEDIS" : $suc;//si son cualquiera de estos tres es cedis
            $store = Stores::where('name',$stor)->value('id');
            $position = Position::where('name',$val['column_values'][2]['text'])->value('id');
            $res = [
                "complete_name"=>$val['name'],
                "id_rc"=>$val['column_values'][0]['text'],
                "_store"=>$store,
                "_position"=>$position,
                "picture"=>$val['column_values'][12]['text'],
                "clasification"=>$val['column_values'][13]['text'],
                "ingress"=>$val['column_values'][7]['text'] == "" ? "1999-01-01" : $val['column_values'][7]['text']  ,
                "acitve"=>$activ
            ];
            return $res;
        }, $row);
        $usersdb = Staff::where('acitve',1)->select('complete_name','id_rc','_store','_position','picture','clasification','ingress','acitve')->get()->toArray();
        $textusermon  = array_map(function($val){ return implode(',',$val);},$usersmon);
        $textuserdb = array_map(function($val){ return implode(',',$val);},$usersdb);
        $diff = array_diff($textusermon,$textuserdb);
        $newuser = array_map(function($val){return  explode(',',$val); },$diff);
        $difef = array_map(function($val){
            $res = [
                "complete_name"=>$val[0],
                "id_rc"=>$val[1],
                "_store"=>$val[2],
                "_position"=>$val[3],
                "picture"=>$val[4],
                "clasification"=>$val[5],
                "ingress"=>$val[6]  ,
                "acitve"=>$val[7]
            ];
            return mb_convert_encoding($res,'UTF-8'); },$newuser);
        $upduse = array_values($difef);

        $staff = Staff::upsert($upduse,['id_rc'],['complete_name','_store','_position','picture','clasification','ingress','acitve']);

        $diffsta = array_diff($textuserdb,$textusermon);
        $userstat = array_map(function($val){return  explode(',',$val); },$diffsta);
        $difefstat = array_map(function($val){
            $res = [
                "complete_name"=>$val[0],
                "id_rc"=>$val[1],
                "_store"=>$val[2],
                "_position"=>$val[3],
                "picture"=>$val[4],
                "clasification"=>$val[5],
                "ingress"=>$val[6]  ,
                "acitve"=>0
            ];
            return mb_convert_encoding($res,'UTF-8'); },$userstat);
        $updu = array_values($difefstat);

        $upstaf = Staff::upsert($updu,['complete_name'],['acitve']);

        $res = [
            "upserts"=>$staff,
            "inactivos"=>$upstaf
        ];
        return response()->json($res,200);
    }

    public function justification(){
        $insertados = [];
        $token = 'eyJhbGciOiJIUzI1NiJ9.eyJ0aWQiOjIwMTgwNzYyMCwidWlkIjoyMDY1ODc3OSwiaWFkIjoiMjAyMi0xMS0yOVQxODoyMToxMS4wMDBaIiwicGVyIjoibWU6d3JpdGUiLCJhY3RpZCI6ODM5Njc1MywicmduIjoidXNlMSJ9.nLLLRUTqG86usf18jqEYHIzf62rYA8Lee2coEEyTxlI';
        $apiUrl = 'https://api.monday.com/v2';
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];
        // $query = 'query {
        // boards (ids: 4403681072) {
        //     items {
        //     id
        //     name
        //     column_values  {
        //         id
        //         title
        //         text
        //     }
        //     }
        // }
        // }';

        $query = 'query {
            items_page_by_column_values ( limit:500 board_id: 4403681072, columns: [{column_id: "estado7", column_values: ["AUTORIZADO"]},{column_id: "estado1", column_values: ["Sin Enviar"]}]) {
              cursor
              items {

                name
                column_values{
                  id
                  text
                }
              }
            }
          }
          ';
        $data = @file_get_contents($apiUrl, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode(['query' => $query]),
        ]
        ]));
        $style = json_decode($data,true);
        $row = $style['data']['items_page_by_column_values']['items'];
        if($row == null){
            return response()->json("No hay justificaciones que replicar");
        }
        foreach( $row as $rows){
            $table[]=$rows['column_values'];
        };
        // return $table;

        foreach($table as $fil){
        $name = $fil[2]['text'];
        $type = $fil[3]['text'];
        $mid = $fil[13]['text'];
        $idmid = DB::table('assist_justification')->where('mid',$mid)->first();
        if($idmid == null){
            $staff = DB::table('staff')->where('complete_name',$name)->value('id');
            if($staff){
            $typ = DB::table('justification_types')->where('name',$fil[3]['text'])->value('id');
                if($typ){
                $ins = [
                    "_staff"=>$staff,
                    "created_at"=>date("Y-m-d H:i:s",strtotime($fil[1]['text'])),
                    "start_date"=>$fil[4]['text'],
                    "final_date"=>$fil[5]['text'],
                    "_type"=>$typ,
                    "percentage"=>intval($fil[10]['text']),
                    "notes"=>$fil[6]['text'],
                    "mid"=>intval($fil[13]['text']),
                ];
                // return $ins;
                $dbins = DB::table('assist_justification')->insert($ins);
                $insertados[] = $ins;
                }else{$fail[]="No se encuentra el tipo de justificiacion ".$type;}
            }else{$fail[]= "No se encontro el usuario ".$name;}
        }
        }
        $res = [
        "registros"=>count($insertados),
        "insertados"=>$insertados
        ];
        return response()->json($res);
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

    public function updatestaff(){
        $todes = 'query {
            boards (ids: 4658499281) {
            columns {
                id
                title
                }
            }
        }
        ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $result = array_filter($columnas, function ($element) {
            return isset($element['title']) && $element['title'] === 'quien(es)';
        });//se buscan solo los quw son son quienes

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
            $agent[] = $agente['name']."-".$agente['column_values'][0]['text'];//se selecionan todos los agentes
        }
        foreach($result as $res){//inicia foreach de las columnas desplegables
        $ids = $res['id'];//se sacan sus ids
        $query = 'mutation {
            change_simple_column_value (item_id:4658502171, board_id:4658499281, column_id:'.$ids.', value: "daleatucuerpo, alegriamakarena") {
                id
            }
        }';//query de mutacion para sacar los agentes que tienen las columnas desplegables
        $monval = $this->apimon($query);//se recibe
        $eri = $monval['error_message'];//se reciben solo los agentes en string
        $ror = substr($eri, strpos($eri,"{"));//se quita el mensaje de eroor
        $err = explode(",",$ror);//se convierte en arreglo
        $art = [];
        foreach($err as $row){//se hace un foreach de el arreglo para generar los que son
        $ala = substr($row,strpos($row,":"));//se quitan los dos puntos de su llave
        $auma = str_replace(": ","",$ala);//se remplazan por nada para poder obtener solo los nombres y se almacena en la variable auma
        $art []= str_replace("}","",$auma);
        }
        $idcol [] = ["id"=>$ids,"colaboradores"=>$art];
        }
        foreach($idcol as $iscol){
        $adam = $iscol['id'];
        $warlok = $iscol['colaboradores'];
        $out = array_diff($agent,$warlok);//se hace diferencia de agentes contra aumas
        $ars = implode(",",$out);
        $inser = 'mutation {
                change_simple_column_value(item_id:4658502171, board_id:4658499281, column_id: '.$adam.', value: "'.$ars.'", create_labels_if_missing: true) {
                id
                }
            }';//se genera el query para insertar cada uno de los que faltan
            $dependientes = $this->apimon($inser);//se insertan
            };

        // foreach($out as $inse){//se genera un foreach de cada uno de ellos para poder actualizarlos
        //   $inser = 'mutation {
        //       change_simple_column_value(item_id:4658502171, board_id:4658499281, column_id: '.$adam.', value: "'.$inse.'", create_labels_if_missing: true) {
        //       id
        //       }
        //   }';//se genera el query para insertar cada uno de los que faltan
        //   $dependientes = $this->apimon($inser);//se insertan
        //   };
        // }
        return $ars;
    }

    public function updatestaffiop(){

        $todes = 'query {
            boards (ids: 4653825876) {
            columns {
                id
                title
                }
            }
        }
        ';
        $col = $this->apimon($todes);//se buscan todos los ids de las columnas de tipo desplegable
        $columnas = $col['data']['boards'][0]['columns'];//se genera
        $result = array_filter($columnas, function ($element) {
            return isset($element['title']) && $element['title'] === 'quien(es)';
        });//se buscan solo los quw son son quienes

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
            $agent[] = $agente['name']."-".$agente['column_values'][0]['text'];//se selecionan todos los agentes
        }
        foreach($result as $res){//inicia foreach de las columnas desplegables
        $ids = $res['id'];//se sacan sus ids
        $query = 'mutation {
            change_simple_column_value (item_id:4708651048, board_id:4653825876, column_id:'.$ids.', value: "daleatucuerpo, alegriamakarena") {
                id
            }
        }';//query de mutacion para sacar los agentes que tienen las columnas desplegables
        $monval = $this->apimon($query);//se recibe
        $eri = $monval['error_message'];//se reciben solo los agentes en string
        $ror = substr($eri, strpos($eri,"{"));//se quita el mensaje de eroor
        $err = explode(",",$ror);//se convierte en arreglo
        $art = [];
        foreach($err as $row){//se hace un foreach de el arreglo para generar los que son
        $ala = substr($row,strpos($row,":"));//se quitan los dos puntos de su llave
        $auma = str_replace(": ","",$ala);//se remplazan por nada para poder obtener solo los nombres y se almacena en la variable auma
        $art []= str_replace("}","",$auma);
        }
        $idcol [] = ["id"=>$ids,"colaboradores"=>$art];
        }
        foreach($idcol as $iscol){
        $adam = $iscol['id'];
        $warlok = $iscol['colaboradores'];
        $out = array_diff($agent,$warlok);//se hace diferencia de agentes contra aumas
        $ars = implode(",",$out);
        $inser = 'mutation {
                change_simple_column_value(item_id:4708651048, board_id:4653825876, column_id: '.$adam.', value: "'.$ars.'", create_labels_if_missing: true) {
                id
                }
            }';//se genera el query para insertar cada uno de los que faltan
            $dependientes = $this->apimon($inser);//se insertan
            };

        return $ars;
    }


    public function finop($inf){


        $data = [
        'fecha' => $inf['fecha'],
        'puntuacion'=>$inf['puntuacion']." / 100",
        'admin' => $inf['admin'],
        'sucursal' => $inf['sucursal'],
        'total'=> $inf['puntuacion']." /100 ",
        'ppg1' => 'PISO VENTA LIMPIOS',
        'tot1' => $inf['tot1'],
        'per1' => $inf['per1'],
        'obs1' => $inf['obs1'],
        'ppg2' => 'ENTREGA DE RADIOS Y TELEFONOS',
        'tot2' => $inf['tot2'],
        'per2' => $inf['per2'],
        'obs2' => $inf['obs2'],
        'ppg3' => 'ENTREGA TELEFONOS PERSONALES',
        'tot3' => $inf['tot3'],
        'per3' => $inf['per3'],
        'obs3' => $inf['obs3'],
        'ppg4' => 'BANOS Y COMEDOR LIMPIOS',
        'tot4' => $inf['tot4'],
        'per4' => $inf['per4'],
        'obs4' => $inf['obs4'],
        'ppg5' => 'CIERRE SESION EN CAJAS',
        'tot5' => $inf['tot5'],
        'per5' => $inf['per5'],
        'obs5' => $inf['obs5'],
        'ppg6' => 'BODEGA LIMPIA Y ORDENADA',
        'tot6' => $inf['tot6'],
        'per6' => $inf['per6'],
        'obs6' => $inf['obs6'],
        'ppg7' => 'ENTREGA GAFETES A ADMINISTRATIVO',
        'tot7' => $inf['tot7'],
        'per7' => $inf['per7'],
        'obs7' => $inf['obs7'],
        'ppg8' => 'LLENADO DE BITACORA PARTE OPERACIONES',
        'tot8' => $inf['tot8'],
        'per8' => $inf['per8'],
        'obs8' => $inf['obs8'],
        'ppg9' => 'CAJAS LIMPIAS Y ORDENADAS',
        'tot9' => $inf['tot9'],
        'per9' => $inf['per9'],
        'obs9' => $inf['obs9'],
        'ppg10' => 'LLENADO DE BITACORA DE RETIRADAS Y CORTE ARCHIVADO',
        'tot10' => $inf['tot10'],
        'per10' => $inf['per10'],
        'obs10' => $inf['obs10'],
        ];
        $pdf = View::make('finop', $data)->render();

        $sucursal = $inf['sucursal'];

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
        $number = "+525537148456";
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
        $namefile = 'checklist'.$inf['fecha'].$inf['sucursal'].'.pdf';
        $rutacompleta = $carpaud.'/'.'checklist'.$inf['fecha'].$inf['sucursal'].'.pdf';
        file_put_contents($rutacompleta,$output);
        $filename = $this->sendocument($rutacompleta,$namefile,$number);
        if($filename == "enviado"){
        $res = [
            "msg"=>"enviado",
            "archivo"=>$rutacompleta
        ];
        }else{
        $res = [
            "msg"=>"no enviado",
            "archivo"=>$rutacompleta
        ];
        }

        return $res;
    }

    public function dropiopupd(){
        // $query = 'query {
        //     boards (ids: 4738541896) {
        //         items {
        //         id
        //         name
        //         column_values  {
        //             id
        //             title
        //             text
        //         }
        //         }
        //     }
        //     }';
        //  return $col = $this->apimon($query);

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
        $result = array_filter($columnas, function ($element) {
            return isset($element['title']) && $element['title'] === 'QUIEN(ES) NO CUMPLE(N)';
        });//se buscan solo los quw son son quienes

        $agentes = $this->filterstaff();

        foreach($result as $res){//inicia foreach de las columnas desplegables
          $ids = $res['id'];//se sacan sus ids
          $query = 'mutation {
              change_simple_column_value (item_id:4738821251, board_id:4738541896, column_id:'.$ids.', value: "daleatucuerpo, alegriamakarena") {
                  id
              }
          }';//query de mutacion para sacar los agentes que tienen las columnas desplegables
          $monval = $this->apimon($query);//se recibe
          $eri = $monval['error_message'];//se reciben solo los agentes en string
          $ror = substr($eri, strpos($eri,"{"));//se quita el mensaje de eroor
          $err = explode(",",$ror);//se convierte en arreglo
          $art = [];
          foreach($err as $row){//se hace un foreach de el arreglo para generar los que son
          $ala = substr($row,strpos($row,":"));//se quitan los dos puntos de su llave
          $auma = str_replace(": ","",$ala);//se remplazan por nada para poder obtener solo los nombres y se almacena en la variable auma
          $art []= str_replace("}","",$auma);
          }
          $idcol [] = ["id"=>$ids,"colaboradores"=>$art];
        }

        foreach($result as $res){
            $ids = $res['id'];
            $ret = [];
            foreach($agentes as $agente){
                $query = 'mutation {
                    change_simple_column_value (item_id:4738821251, board_id:4738541896, column_id:'.$ids.', value: "'.$agente.'", create_labels_if_missing: true) {
                    id
                    }';
                    $monval  = $this->apimon($query);//se recibe
                    $ret [] = $monval;
            }


        }
        return $ret;

        foreach($idcol as $iscol){
          $adam = $iscol['id'];
          $warlok = $iscol['colaboradores'];
          $out = array_diff($agent,$warlok);//se hace diferencia de agentes contra aumas
          $ars = implode(",",$out);
          $inser = 'mutation {
                 change_simple_column_value(item_id:4658502171, board_id:4658499281, column_id: '.$adam.', value: "'.$ars.'", create_labels_if_missing: true) {
                   id
                   }
            }';//se genera el query para insertar cada uno de los que faltan
            $dependientes = $this->apimon($inser);//se insertan
            };

          // foreach($out as $inse){//se genera un foreach de cada uno de ellos para poder actualizarlos
          //   $inser = 'mutation {
          //       change_simple_column_value(item_id:4658502171, board_id:4658499281, column_id: '.$adam.', value: "'.$inse.'", create_labels_if_missing: true) {
          //       id
          //       }
          //   }';//se genera el query para insertar cada uno de los que faltan
          //   $dependientes = $this->apimon($inser);//se insertan
          //   };
        // }
        return $ars;
    }

    public function filterstaff(){
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
            $agent[] = [
                "nombre"=>$agente['name'],
                "sucursal"=>$agente['column_values'][1]['text']];//se selecionan todos los agentes
        }
        $notin = ["CEDIS","OFICINA","MANTENIMIENTO","TEXCOCO","AUDITORIA/INVENTARIOS"];

        $agnte = array_filter($agent, function($class) use($notin){
            return isset($class['sucursal']) && !in_array($class['sucursal'],$notin);
        });
        // return $agnte;
        $colab = [];
        foreach($agnte as $fil){
            $colab[] = $fil['nombre'];
        };
        return $colab;
    }

    // public function replystaff(){
    //     $upins = [//se guardan registros actualizados o insertados
    //         'inserts'=>[],
    //         'updates'=>[]
    //     ];
    //     $fail = [//contenedor para los fails jaja
    //         'inserts'=>[],
    //         'updates'=>[],
    //         'names'=>[],
    //         'sucursal'=>[]
    //     ];

    //     $token = env('TOKEN_MA');//token monday
    //     $apiUrl = 'https://api.monday.com/v2';//conexion api monday
    //     $headers = ['Content-Type: application/json', 'Authorization: ' . $token];//capeceras monday
    //     // $query = 'query {
    //     // items_by_column_values (board_id: 1520861792, column_id: "estatus", column_value: "ACTIVO") {
    //     //     name,
    //     //     column_values  {
    //     //     id
    //     //     title
    //     //     text
    //     //     }
    //     // }
    //     // }';//se genera consulta graphql para api de monday

    //     $query = 'query {
    //         items_page_by_column_values ( limit:500 board_id: 1520861792, columns: [{column_id: "estatus", column_values: ["ACTIVO"]}]) {
    //           cursor
    //           items {
    //             name
    //             column_values{
    //               id
    //               text
    //             }
    //           }
    //         }
    //       }';
    //     $data = @file_get_contents($apiUrl, false, stream_context_create([
    //     'http' => [
    //         'method' => 'POST',
    //         'header' => $headers,
    //         'content' => json_encode(['query' => $query]),
    //     ]
    //     ]));//conexion api monday
    //     $style = json_decode($data,true);//se decodifica lo que se recibe
    //     $row = $style['data']['items_page_by_column_values']['items'];//recorremos arreglos hasta los que ocuparemos
    //     foreach($row as $rows){//inicio foreach
    //         $user  = $rows['name'];//se toma el nombre
    //         if(strpos($user, '(copy)')){//se valida que no contenga copy ya que puede haver duplicados
    //         $fail['names'][]=["err"=>"Posbible Duplicado","motivo"=>[$user=>"debido a que contiene (copy) y es posible que haya un duplicado" ]];//fail copy
    //         }
    //         $rcid  = $rows['column_values'][0]['text'];// id
    //         $suc = $rows['column_values'][1]['text'];// sucursal
    //         $posi = $rows['column_values'][2]['text'];//puesto
    //         $pic = $rows['column_values'][12]['text'];//picture

    //         $nsu = $suc == "OFICINA" || $suc == "MANTENIMIENTO" || $suc == "AUDITORIA/INVENTARIOS" ? "CEDIS" : $suc;//si son cualquiera de estos tres es cedis
    //         $sucursal = DB::table('stores')->where('name',$nsu)->first();//se busca sucursal
    //         if($sucursal){//se verifica que existe
    //             $bpos = DB::table('positions')->where('name',$posi)->value('id');//se busca posision
    //             if($bpos){//se valida que exista
    //                 $bus = DB::table('staff')->where('id_rc',$rcid)->value('id');//se busca el nombre en caso de que haya
    //                 if($bus){//se valida que exise
    //                     $val = DB::table('staff')->where('id',$bus)->where('complete_name',$user)->where('_store',$sucursal->id)->where('_position',$bpos)->where('picture',$pic)->first();//si existe se compara que tenga la misma informacion
    //                     if($val == null){//en caso de que no
    //                     $update =DB::table('staff')->where('id',$bus)->update(['complete_name'=>$user, '_store'=>$sucursal->id, '_position'=>$bpos, 'picture'=>$pic]);//se actuzliza lainformacion de el colaborador
    //                     $upins['updates'][]="Se Actualizo el usuario ".$user;//se guarda en el arreglo de goals
    //                     }
    //                 }else{//en caso de que no exista
    //                     if($rcid == ""){//se verifica que tenga id de el reloj checador
    //                         $fail['inserts'][]="el colaborador ".$user." aun no tiene id";//en caso de no tener se guarda en el arreglo de inserts
    //                     }else{
    //                         $existid = DB::table('staff')->where('id_rc',$rcid)->first();//SE VERIFICA QUE NO EXISTA UN VALOR DUPLICADO EN EL ID DEL CHECADOR
    //                         if($existid){//si existe
    //                             $fail['inserts'][]=$existid->id_rc." ya esta asignado a otro colaborador ".$existid->complete_name;//se contiene en fails
    //                         }else{
    //                             $insert = DB::table('staff')->insert(['complete_name'=>$user,'id_rc'=>$rcid,'_store'=>$sucursal->id, '_position'=>$bpos, 'picture'=>$pic]);// si no tiene se inserta en la tabla
    //                             $upins['inserts'][] ="Usuario ".$user." insertado";//se guarda en el arreglo de goals
    //                         }
    //                     }
    //                 }
    //             }
    //         }else{$fail['sucursal'][]="No existe la sucursal ".$nsu." de el usuario ".$user; }//fin de if sucursal
    //     }
    //     $res = [
    //     "registros"=>[
    //         'inserts'=>count($upins['inserts']),
    //         'updates'=>count($upins['updates'])
    //     ],
    //     "goals"=>$upins,
    //     "fails"=>$fail
    //     ];
    //     return response()->json($res);
    // }

    public function Index(){
        $token = env('TOKEN_MA');//token monday
        $apiUrl = 'https://api.monday.com/v2';//conexion api monday
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];//capeceras monday
        $query = 'query {
            items_page_by_column_values ( limit:500 board_id: 1520861792, columns: [{column_id: "estatus", column_values: ["ACTIVO"]}]) {
              cursor
              items {
                name
                column_values{
                  id
                  text
                  column{
                    title
                    }
                }
              }
            }
          }';
        $data = @file_get_contents($apiUrl, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode(['query' => $query]),
        ]
        ]));//conexion api monday
        $style = json_decode($data,true);//se decodifica lo que se recibe
        $row = $style['data']['items_page_by_column_values']['items'];//recorremos arreglos hasta los que ocuparemos
        $usersmon = array_map(function($val){
            $suc = $val['column_values'][1]['text'];
            $activ = $val['column_values'][3]['text'] == "ACTIVO" ? 1 : 0;
            $stor =  $suc == "OFICINA" || $suc == "MANTENIMIENTO" || $suc == "AUDITORIA/INVENTARIOS" ? "CEDIS" : $suc;//si son cualquiera de estos tres es cedis
            $store = Stores::where('name',$stor)->first();
            $position = Position::where('name',$val['column_values'][2]['text'])->first();
            $res = [
                "complete_name"=>$val['name'],
                "id_rc"=>$val['column_values'][0]['text'],
                "_store"=>$store->id,
                "_position"=>$position ? $position->id  : null ,
                "picture"=>$val['column_values'][12]['text'],
                "stores"=>$store,
                "position"=>$position,
                "clasification"=>$val['column_values'][13]['text'],
                "ingress"=>$val['column_values'][7]['text'] == "" ? "1999-01-01" : $val['column_values'][7]['text']  ,
                "acitve"=>$activ
            ];
            return $res;
        }, $row);

        $usersdb = Staff::with('stores','position')->where('acitve',1)->select('complete_name','id_rc','_store','_position','picture','clasification','ingress','acitve')->get()->toArray();


        $res = [
            "mon"=>$usersmon,
            "mysq"=>$usersdb,
        ];
        return $res;
    }

    public function staffReply(){
        $upins = [//se guardan registros actualizados o insertados
            'inserts'=>[],
            'updates'=>[]
        ];
        $fail = [//contenedor para los fails jaja
            'inserts'=>[],
            'updates'=>[],
            'names'=>[],
            'sucursal'=>[]
        ];
        $exist=[];

        $token = env('TOKEN_MA');//token monday
        $apiUrl = 'https://api.monday.com/v2';//conexion api monday
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];//capeceras monday
        $query = 'query {
            items_page_by_column_values ( limit:500 board_id: 1520861792, columns: [{column_id: "estatus", column_values: ["ACTIVO"]}]) {
              cursor
              items {
                name
                column_values{
                  id
                  text
                  column{
                    title
                    }
                }
              }
            }
          }';
        $data = @file_get_contents($apiUrl, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => $headers,
            'content' => json_encode(['query' => $query]),
        ]
        ]));//conexion api monday
        $style = json_decode($data,true);//se decodifica lo que se recibe
        $row = $style['data']['items_page_by_column_values']['items'];//recorremos arreglos hasta los que ocuparemos

        $usersmon = array_map(function($val){
            $suc = $val['column_values'][1]['text'];
            $activ = $val['column_values'][3]['text'] == "ACTIVO" ? 1 : 0;
            $stor =  $suc == "OFICINA" || $suc == "MANTENIMIENTO" || $suc == "AUDITORIA/INVENTARIOS" ? "CEDIS" : $suc;//si son cualquiera de estos tres es cedis
            $store = Stores::where('name',$stor)->value('id');
            $position = Position::where('name',$val['column_values'][2]['text'])->value('id');
            $res = [
                "complete_name"=>$val['name'],
                "id_rc"=>$val['column_values'][0]['text'],
                "_store"=>$store,
                "_position"=>$position,
                "picture"=>$val['column_values'][12]['text'],
                "clasification"=>$val['column_values'][13]['text'],
                "ingress"=>$val['column_values'][7]['text'] == "" ? "1999-01-01" : $val['column_values'][7]['text']  ,
                "acitve"=>$activ
            ];
            return $res;
        }, $row);
        $usersdb = Staff::where('acitve',1)->select('complete_name','id_rc','_store','_position','picture','clasification','ingress','acitve')->get()->toArray();
        $textusermon  = array_map(function($val){ return implode(',',$val);},$usersmon);
        $textuserdb = array_map(function($val){ return implode(',',$val);},$usersdb);
        $diff = array_diff($textusermon,$textuserdb);
        $newuser = array_map(function($val){return  explode(',',$val); },$diff);
        $difef = array_map(function($val){
            $res = [
                "complete_name"=>$val[0],
                "id_rc"=>$val[1],
                "_store"=>$val[2],
                "_position"=>$val[3],
                "picture"=>$val[4],
                "clasification"=>$val[5],
                "ingress"=>$val[6]  ,
                "acitve"=>$val[7]
            ];
            return mb_convert_encoding($res,'UTF-8'); },$newuser);
        $upduse = array_values($difef);
        // return $upduse;

        foreach($upduse as $usnw){
            $existe = Staff::where('complete_name',$usnw)->first();
            if($existe){
                $existe->id_rc = $usnw['id_rc'];
                $existe->_store = $usnw['_store'];
                $existe->_position = $usnw['_position'];
                $existe->picture = $usnw['picture'];
                $existe->clasification = $usnw['clasification'];
                $existe->ingress = $usnw['ingress'];
                $existe->acitve = $usnw['acitve'];
                $existe->save();
                $res = $existe->fresh();

                $exist['Actualizados'][]=$res;
            }else{
                $insert= Staff::insert($usnw);
                if($insert){
                    $exist['Insertados'][]=$usnw;
                }
            }
        }
        return response()->json($exist,200);
    }
}



//mx100-cedis-mkrqpwcczk.dynamic-m.com:1025/Assist/public/api/webhook

