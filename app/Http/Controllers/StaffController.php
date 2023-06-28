<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class StaffController extends Controller
{
  public function __construct(){

  }


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
      items_by_column_values (board_id: 1520861792, column_id: "estatus", column_value: "ACTIVO") {
        name,
        column_values  {
          id
          title
          text
        }
      }
    }';//se genera consulta graphql para api de monday
    $data = @file_get_contents($apiUrl, false, stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => $headers,
        'content' => json_encode(['query' => $query]),
      ]
    ]));//conexion api monday
    $style = json_decode($data,true);//se decodifica lo que se recibe
    $row = $style['data']['items_by_column_values'];//recorremos arreglos hasta los que ocuparemos
    foreach($row as $rows){//inicio foreach
        $user  = $rows['name'];//se toma el nombre
        if(strpos($user, '(copy)')){//se valida que no contenga copy ya que puede haver duplicados
           $fail['names'][]=["err"=>"Posbible Duplicado","motivo"=>[$user=>"debido a que contiene (copy) y es posible que haya un duplicado" ]];//fail copy
        }
        $rcid  = $rows['column_values'][0]['text'];// id
        $suc = $rows['column_values'][1]['text'];// sucursal
        $posi = $rows['column_values'][2]['text'];//puesto
        $pic = $rows['column_values'][12]['text'];//picture

        $nsu = $suc == "OFICINA" || $suc == "MANTENIMIENTO" || $suc == "AUDITORIA/INVENTARIOS" ? "CEDIS" : $suc;//si son cualquiera de estos tres es cedis
        $sucursal = DB::table('stores')->where('name',$nsu)->first();//se busca sucursal
        if($sucursal){//se verifica que existe
            $bpos = DB::table('positions')->where('name',$posi)->value('id');//se busca posision
            if($bpos){//se valida que exista
                $bus = DB::table('staff')->where('complete_name',$user)->value('id');//se busca el nombre en caso de que haya
                if($bus){//se valida que exise
                    $val  = DB::table('staff')->where('id',$bus)->where('id_rc',$rcid)->where('_store',$sucursal->id)->where('_position',$bpos)->where('picture',$pic)->first();//si existe se compara que tenga la misma informacion
                    if($val == null){//en caso de que no
                    $update =DB::table('staff')->where('id',$bus)->update(['id_rc'=>$rcid, '_store'=>$sucursal->id, '_position'=>$bpos, 'picture'=>$pic]);//se actuzliza lainformacion de el colaborador
                    $upins['updates'][]="Se Actualizo el usuario ".$user;//se guarda en el arreglo de goals
                    }
                }else{//en caso de que no exista
                    if($rcid == ""){//se verifica que tenga id de el reloj checador
                        $fail['inserts'][]="el colaborador ".$user." aun no tiene id";//en caso de no tener se guarda en el arreglo de inserts
                    }else{
                        $existid = DB::table('staff')->where('id_rc',$rcid)->first();//SE VERIFICA QUE NO EXISTA UN VALOR DUPLICADO EN EL ID DEL CHECADOR
                        if($existid){//si existe
                            $fail['inserts'][]="ID_RC ya esta asignado a otro colaborador ".$rcid;//se contiene en fails
                        }else{
                            $insert = DB::table('staff')->insert(['complete_name'=>$user,'id_rc'=>$rcid,'_store'=>$sucursal->id, '_position'=>$bpos, 'picture'=>$pic]);// si no tiene se inserta en la tabla
                            $upins['inserts'][] ="Usuario ".$user." insertado";//se guarda en el arreglo de goals
                        }
                    }
                }
            }
        }else{$fail['sucursal'][]="No existe la sucursal ".$nsu." de el usuario ".$user; }//fin de if sucursal
    }
     $res = [
      "registros"=>[
        'inserts'=>count($upins['inserts']),
        'updates'=>count($upins['updates'])
      ],
      "goals"=>$upins,
      "fails"=>$fail
    ];
    return response()->json($res);
  }

  public function justification(){
    $insertados = [];
    $token = 'eyJhbGciOiJIUzI1NiJ9.eyJ0aWQiOjIwMTgwNzYyMCwidWlkIjoyMDY1ODc3OSwiaWFkIjoiMjAyMi0xMS0yOVQxODoyMToxMS4wMDBaIiwicGVyIjoibWU6d3JpdGUiLCJhY3RpZCI6ODM5Njc1MywicmduIjoidXNlMSJ9.nLLLRUTqG86usf18jqEYHIzf62rYA8Lee2coEEyTxlI';
    $apiUrl = 'https://api.monday.com/v2';
    $headers = ['Content-Type: application/json', 'Authorization: ' . $token];
    $query = 'query {
      boards (ids: 4403681072) {
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

    $data = @file_get_contents($apiUrl, false, stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => $headers,
        'content' => json_encode(['query' => $query]),
      ]
    ]));
    $style = json_decode($data,true);
    $row = $style['data']['boards'][0]['items'];
    if($row == null){
        return response()->json("No hay justificaciones que replicar");
    }
    foreach( $row as $rows){
        $table[]=$rows['column_values'];
    };
    foreach($table as $fil){
      $name = $fil[1]['text'];
      $type = $fil[2]['text'];
      $mid = $fil[7]['text'];
      $idmid = DB::table('assist_justification')->where('mid',$mid)->first();
      if($idmid == null){
        $staff = DB::table('staff')->where('complete_name',$name)->value('id');
        if($staff){
          $typ = DB::table('justification_types')->where('name',$fil[2]['text'])->value('id');
            if($typ){
              $ins = [
                "_staff"=>$staff,
                "created_at"=>date("Y-m-d H:i:s",strtotime($fil[0]['text'])),
                "start_date"=>$fil[3]['text'],
                "final_date"=>$fil[4]['text'],
                "_type"=>$typ,
                "percentage"=>intval($fil[5]['text']),
                "notes"=>$fil[6]['text'],
                "mid"=>intval($fil[7]['text']),
              ];
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

  public function checklistiop(){
    $fail = [];
    $ins = [];
    $ens = [];

    $query = 'query {
        items_by_column_values (board_id: 4653825876, column_id: "estado1", column_value: "SIN ENVIAR") {
            id
            column_values{
                id
                title
                text
            }
        }
    }';//se genera consulta graphql para api de monday
    $monval = $this->apimon($query);
    $rows = $monval['data']['items_by_column_values'];
    if($rows){
        foreach($rows as $row){
            $ids = $row['id'];
            $existid =DB::table('checklistiop')->where('id_mon',$ids)->first();
            if($existid){
            }else{
                $suc = $row['column_values'][0]['text'];
                $idsuc = DB::table('stores')->where('name',$suc)->value('id');
                if($idsuc){
                    $admon = $row['column_values'][2]['text'];
                    $idadm = DB::table('staff')->where('complete_name',$admon)->value('id');
                    if($idadm){
                        $crated = $row['column_values'][1]['text'];
                        $datetime_utc = strtotime($crated) - 3600;
                        date_default_timezone_set(date_default_timezone_get());
                        $created = date('Y-m-d H:i:s', $datetime_utc);
                        $cated = date('Y-m-d H_i_s', $datetime_utc);
                        $evidence  = json_encode($row['column_values'][54]['text']);
                        $cal = [
                            "pg1" => $row['column_values'][3]['text'] ,
                            "pg2" => $row['column_values'][6]['text'] ,
                            "pg3" => $row['column_values'][9]['text'] ,
                            "pg4" => $row['column_values'][12]['text'],
                            "pg5" => $row['column_values'][15]['text'],
                            "pg6" => $row['column_values'][18]['text'],
                            "pg7" => $row['column_values'][21]['text'],
                            "pg8" => $row['column_values'][24]['text'],
                            "pg9" => $row['column_values'][27]['text'],
                            "pg10" => $row['column_values'][30]['text'],
                            "pg11" => $row['column_values'][33]['text'],
                            "pg12" => $row['column_values'][36]['text'],
                            "pg13" => $row['column_values'][39]['text'],
                            "pg14" => $row['column_values'][42]['text'],
                            "pg15" => $row['column_values'][45]['text'],
                            "pg16" => $row['column_values'][48]['text'],
                            "pg17" => $row['column_values'][51]['text']
                        ];
                        $cali = $this->calificacioniop($cal);
                        $ins = [
                            "_store"=>$idsuc,
                            "created"=>$created,
                            "_admon"=>$idadm,
                            "quiz"=>json_encode([
                                $row['column_values'][3]['title'] =>  ["criterio"=>$row['column_values'][3]['text'] ,"observaciones"=>$row['column_values'][4]['text'],"quien"=>$row['column_values'][5]['text']],
                                $row['column_values'][6]['title'] =>  ["criterio"=>$row['column_values'][6]['text'] ,"observaciones"=>$row['column_values'][7]['text'],"quien"=>$row['column_values'][8]['text']],
                                $row['column_values'][9]['title'] =>  ["criterio"=>$row['column_values'][9]['text'] ,"observaciones"=>$row['column_values'][10]['text'],"quien"=>$row['column_values'][11]['text']],
                                $row['column_values'][12]['title'] => ["criterio"=>$row['column_values'][12]['text'],"observaciones"=>$row['column_values'][13]['text'],"quien"=>$row['column_values'][14]['text']],
                                $row['column_values'][15]['title'] => ["criterio"=>$row['column_values'][15]['text'],"observaciones"=>$row['column_values'][16]['text'],"quien"=>$row['column_values'][17]['text']],
                                $row['column_values'][18]['title'] => ["criterio"=>$row['column_values'][18]['text'],"observaciones"=>$row['column_values'][19]['text'],"quien"=>$row['column_values'][20]['text']],
                                $row['column_values'][21]['title'] => ["criterio"=>$row['column_values'][21]['text'],"observaciones"=>$row['column_values'][22]['text'],"quien"=>$row['column_values'][23]['text']],
                                $row['column_values'][24]['title'] => ["criterio"=>$row['column_values'][24]['text'],"observaciones"=>$row['column_values'][25]['text'],"quien"=>$row['column_values'][26]['text']],
                                $row['column_values'][27]['title'] => ["criterio"=>$row['column_values'][27]['text'],"observaciones"=>$row['column_values'][28]['text'],"quien"=>$row['column_values'][29]['text']],
                                $row['column_values'][30]['title'] => ["criterio"=>$row['column_values'][30]['text'],"observaciones"=>$row['column_values'][31]['text'],"quien"=>$row['column_values'][32]['text']],
                                $row['column_values'][33]['title'] => ["criterio"=>$row['column_values'][33]['text'],"observaciones"=>$row['column_values'][34]['text'],"quien"=>$row['column_values'][35]['text']],
                                $row['column_values'][36]['title'] => ["criterio"=>$row['column_values'][36]['text'],"observaciones"=>$row['column_values'][37]['text'],"quien"=>$row['column_values'][38]['text']],
                                $row['column_values'][39]['title'] => ["criterio"=>$row['column_values'][39]['text'],"observaciones"=>$row['column_values'][40]['text'],"quien"=>$row['column_values'][41]['text']],
                                $row['column_values'][42]['title'] => ["criterio"=>$row['column_values'][42]['text'],"observaciones"=>$row['column_values'][43]['text'],"quien"=>$row['column_values'][44]['text']],
                                $row['column_values'][45]['title'] => ["criterio"=>$row['column_values'][45]['text'],"observaciones"=>$row['column_values'][46]['text'],"quien"=>$row['column_values'][47]['text']],
                                $row['column_values'][48]['title'] => ["criterio"=>$row['column_values'][48]['text'],"observaciones"=>$row['column_values'][49]['text'],"quien"=>$row['column_values'][50]['text']],
                                $row['column_values'][51]['title'] => ["criterio"=>$row['column_values'][51]['text'],"observaciones"=>$row['column_values'][52]['text'],"quien"=>$row['column_values'][53]['text']],
                            ]),
                            "evidence"=>$evidence,
                            "id_mon"=>$ids,
                            "calification"=> $cali."/"."100"
                            ];
                        $insert = DB::table('checklistiop')->insert($ins);
                        if($insert){
                            $ens[] = $ids;
                            $changtext = 'mutation {change_simple_column_value (item_id:'.$ids.', board_id:4653825876, column_id:"texto35", value: "'.$cali."/"."100".'") {id}}';
                            $chagesta = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4653825876, column_values: "{\"estado1\" : {\"label\" : \"Enviado\"}}") {id}}';
                            $chan = $this->apimon($changtext);
                            $chans = $this->apimon($chagesta);
                            $inf = [
                                'fecha'=>$cated,
                                'puntuacion'=>$cali,
                                'admin'=>$admon,
                                'sucursal'=>$suc,
                                'tot1'=>$row['column_values'][3]['text'],
                                'obs1'=>$row['column_values'][4]['text'],
                                'per1'=>$row['column_values'][5]['text'],
                                'tot2'=>$row['column_values'][6]['text'],
                                'obs2'=>$row['column_values'][7]['text'],
                                'per2'=>$row['column_values'][8]['text'],
                                'tot3'=>$row['column_values'][9]['text'],
                                'obs3'=>$row['column_values'][10]['text'],
                                'per3'=>$row['column_values'][11]['text'],
                                'tot4'=>$row['column_values'][12]['text'],
                                'obs4'=>$row['column_values'][13]['text'],
                                'per4'=>$row['column_values'][14]['text'],
                                'tot5'=>$row['column_values'][15]['text'],
                                'obs5'=>$row['column_values'][16]['text'],
                                'per5'=>$row['column_values'][17]['text'],
                                'tot6'=>$row['column_values'][18]['text'],
                                'obs6'=>$row['column_values'][19]['text'],
                                'per6'=>$row['column_values'][20]['text'],
                                'tot7'=>$row['column_values'][21]['text'],
                                'obs7'=>$row['column_values'][22]['text'],
                                'per7'=>$row['column_values'][23]['text'],
                                'tot8'=>$row['column_values'][24]['text'],
                                'obs8'=>$row['column_values'][25]['text'],
                                'per8'=>$row['column_values'][26]['text'],
                                'tot9'=>$row['column_values'][27]['text'],
                                'obs9'=>$row['column_values'][28]['text'],
                                'per9'=>$row['column_values'][29]['text'],
                                'tot10'=>$row['column_values'][30]['text'],
                                'obs10'=>$row['column_values'][31]['text'],
                                'per10'=>$row['column_values'][32]['text'],
                                'tot11'=>$row['column_values'][33]['text'],
                                'obs11'=>$row['column_values'][34]['text'],
                                'per11'=>$row['column_values'][35]['text'],
                                'tot12'=>$row['column_values'][36]['text'],
                                'obs12'=>$row['column_values'][37]['text'],
                                'per12'=>$row['column_values'][38]['text'],
                                'tot13'=>$row['column_values'][39]['text'],
                                'obs13'=>$row['column_values'][40]['text'],
                                'per13'=>$row['column_values'][41]['text'],
                                'tot14'=>$row['column_values'][42]['text'],
                                'obs14'=>$row['column_values'][43]['text'],
                                'per14'=>$row['column_values'][44]['text'],
                                'tot15'=>$row['column_values'][45]['text'],
                                'obs15'=>$row['column_values'][46]['text'],
                                'per15'=>$row['column_values'][47]['text'],
                                'tot16'=>$row['column_values'][48]['text'],
                                'obs16'=>$row['column_values'][49]['text'],
                                'per16'=>$row['column_values'][50]['text'],
                                'tot17'=>$row['column_values'][51]['text'],
                                'obs17'=>$row['column_values'][52]['text'],
                                'per17'=>$row['column_values'][53]['text'],
                            ];
                            $pdf = $this->pdi($inf);
                            if($pdf['msg'] == "enviado"){
                                //  $data = $pdf['archivo'];
                                //  $document = file_get_contents($data);
                                //  $doc = base64_encode($document);
                                 $chagemsg = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4653825876, column_values: "{\"estado43\" : {\"label\" : \"ENVIADO\"}}") {id}}';
                                 $chanms = $this->apimon($chagemsg);
                                //  $addfile = 'mutation ($file:file!) { add_file_to_column (item_id: '.$ids.', column_id: "archivo3", file: '.$doc.') {id}}';
                                // $chanfile = $this->apimonfile($addfile); //en algun moento funcionara
                                }
                                // return $chanfile;
                        }
                    }else{
                        $fail[]="El admon ".$admon." no existe";
                    }
                }else{
                    $fail[]="la sucursal ".$suc." no existe";
                }
            }
        }
        return response()->json("REGISTROS INSERTADOS = ".count($ens));//recorremos arreglos hasta los que ocuparemo
    }else {
        return response()->json("No hay registros");
    }
  }

  public function calificacioniop($cali){
    $pg1 = $cali['pg1'] == "CUMPLE" ? 5 : 0;
    $pg2 = $cali['pg2'] == "CUMPLE" ? 5 : 0;
    $pg3 = $cali['pg3'] == "CUMPLE" ? 5 : 0;
    $pg4 = $cali['pg4'] == "CUMPLE" ? 5 : 0;
    $pg5 = $cali['pg5'] == "CUMPLE" ? 5 : 0;
    $pg6 = $cali['pg6'] == "CUMPLE" ? 5 : 0;
    $pg7 = $cali['pg7'] == "CUMPLE" ? 5 : 0;
    $pg8 = $cali['pg8'] == "CUMPLE" ? 5 : 0;
    $pg9 = $cali['pg9'] == "CUMPLE" ? 10 : 0;
    $pg10 = $cali['pg10'] == "CUMPLE" ? 5 : 0;
    $pg11 = $cali['pg11'] == "CUMPLE" ? 5 : 0;
    $pg12 = $cali['pg12'] == "CUMPLE" ? 5 : 0;
    $pg13 = $cali['pg13'] == "CUMPLE" ? 5 : 0;
    $pg14 = $cali['pg14'] == "CUMPLE" ? 8 : 0;
    $pg15 = $cali['pg15'] == "CUMPLE" ? 6 : 0;
    $pg16 = $cali['pg16'] == "CUMPLE" ? 6 : 0;
    $pg17 = $cali['pg17'] == "CUMPLE" ? 10 : 0;

    $TOTAL =  $pg1 + $pg2 + $pg3 + $pg4 + $pg5 + $pg6 + $pg7 + $pg8 + $pg9 + $pg10 + $pg11 + $pg12 + $pg13 + $pg14 + $pg15 + $pg16 + $pg17;


    return $TOTAL;
  }

  public function pdi($inf){


      $data = [
        'fecha' => $inf['fecha'],
        'puntuacion'=>$inf['puntuacion']." / 100",
        'admin' => $inf['admin'],
        'sucursal' => $inf['sucursal'],
        'total'=> $inf['puntuacion']." /100 ",
        'ppg1' => 'HORARIO DE DESAYUNO CORRECTO',
        'tot1' => $inf['tot1'],
        'per1' => $inf['per1'],
        'obs1' => $inf['obs1'],
        'ppg2' => 'RETROALIMENTACION DE INFORME',
        'tot2' => $inf['tot2'],
        'per2' => $inf['per2'],
        'obs2' => $inf['obs2'],
        'ppg3' => 'PERSONAL CON PRESENTACION E HIGIENE',
        'tot3' => $inf['tot3'],
        'per3' => $inf['per3'],
        'obs3' => $inf['obs3'],
        'ppg4' => 'PERSONAL COMPLETO',
        'tot4' => $inf['tot4'],
        'per4' => $inf['per4'],
        'obs4' => $inf['obs4'],
        'ppg5' => 'DISPOSITIVOS OPERACIONALES CON CARGA',
        'tot5' => $inf['tot5'],
        'per5' => $inf['per5'],
        'obs5' => $inf['obs5'],
        'ppg6' => 'CAMBIO SUFICIENTE EN CAJA',
        'tot6' => $inf['tot6'],
        'per6' => $inf['per6'],
        'obs6' => $inf['obs6'],
        'ppg7' => 'FECHA CORRECTA EN CAJA',
        'tot7' => $inf['tot7'],
        'per7' => $inf['per7'],
        'obs7' => $inf['obs7'],
        'ppg8' => 'SUMINISTROS EN CAJA',
        'tot8' => $inf['tot8'],
        'per8' => $inf['per8'],
        'obs8' => $inf['obs8'],
        'ppg9' => 'ENTREGA CELULAR PERSONAL',
        'tot9' => $inf['tot9'],
        'per9' => $inf['per9'],
        'obs9' => $inf['obs9'],
        'ppg10' => 'PISO LIMPIO',
        'tot10' => $inf['tot10'],
        'per10' => $inf['per10'],
        'obs10' => $inf['obs10'],
        'ppg11' => 'BANO Y COMEDOR LIMPIOS',
        'tot11' => $inf['tot11'],
        'per11' => $inf['per11'],
        'obs11' => $inf['obs11'],
        'ppg12' => 'MUSICA AMBIENTAL',
        'tot12' => $inf['tot12'],
        'per12' => $inf['per12'],
        'obs12' => $inf['obs12'],
        'ppg13' => 'MUEBLERIA Y MERCANCIA LIMPIOS',
        'tot13' => $inf['tot13'],
        'per13' => $inf['per13'],
        'obs13' => $inf['obs13'],
        'ppg14' => 'EQUIPO DE COMPUTO EN BUENAS CONDICIONES',
        'tot14' => $inf['tot14'],
        'per14' => $inf['per14'],
        'obs14' => $inf['obs14'],
        'ppg15' => 'EXHIBICIONES LLENAS',
        'tot15' => $inf['tot15'],
        'per15' => $inf['per15'],
        'obs15' => $inf['obs15'],
        'ppg16' => 'PUBLICIDAD',
        'tot16' => $inf['tot16'],
        'per16' => $inf['per16'],
        'obs16' => $inf['obs16'],
        'ppg17' => 'ALMACEN LIMPIO Y ORDENADO',
        'tot17' => $inf['tot17'],
        'per17' => $inf['per17'],
        'obs17' => $inf['obs17'],
      ];
      $pdf = View::make('testPDF', $data)->render();

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


  public function checklistfinop(){
    $fail = [];
    $ins = [];
    $ens = [];

    $query = 'query {
        items_by_column_values (board_id: 4658499281, column_id: "estado", column_value: "SIN ENVIAR") {
            id
            column_values{
                id
                title
                text
            }
        }
    }';//se genera consulta graphql para api de monday
    $monval = $this->apimon($query);
    $rows = $monval['data']['items_by_column_values'];
    if($rows){
        foreach($rows as $row){
            $ids = $row['id'];
            $existid =DB::table('checklistfinop')->where('id_mon',$ids)->first();
            if($existid){
            }else{
                $suc = $row['column_values'][1]['text'];
                $idsuc = DB::table('stores')->where('name',$suc)->value('id');
                if($idsuc){
                    $admon = $row['column_values'][3]['text'];
                    $idadm = DB::table('staff')->where('complete_name',$admon)->value('id');
                    if($idadm){
                        $crated = $row['column_values'][2]['text'];
                        $datetime_utc = strtotime($crated) - 3600;
                        date_default_timezone_set(date_default_timezone_get());
                        $created = date('Y-m-d H:i:s', $datetime_utc);
                        $cated = date('Y-m-d H_i_s', $datetime_utc);
                        $evidence  = json_encode($row['column_values'][34]['text']);
                        $cal = [
                            "pg1" => $row['column_values'][4]['text'] ,
                            "pg2" => $row['column_values'][7]['text'] ,
                            "pg3" => $row['column_values'][10]['text'] ,
                            "pg4" => $row['column_values'][13]['text'],
                            "pg5" => $row['column_values'][16]['text'],
                            "pg6" => $row['column_values'][19]['text'],
                            "pg7" => $row['column_values'][22]['text'],
                            "pg8" => $row['column_values'][25]['text'],
                            "pg9" => $row['column_values'][28]['text'],
                            "pg10" => $row['column_values'][31]['text']
                        ];

                        $cali = $this->calificacionfinop($cal);
                        $ins = [
                            "_store"=>$idsuc,
                            "created"=>$created,
                            "_admon"=>$idadm,
                            "quiz"=>json_encode([
                                $row['column_values'][4]['title'] =>  ["criterio"=>$row['column_values'][4]['text'] ,"observaciones"=>$row['column_values'][5]['text'],"quien"=>$row['column_values'][6]['text']],
                                $row['column_values'][7]['title'] =>  ["criterio"=>$row['column_values'][7]['text'] ,"observaciones"=>$row['column_values'][8]['text'],"quien"=>$row['column_values'][9]['text']],
                                $row['column_values'][10]['title'] =>  ["criterio"=>$row['column_values'][10]['text'] ,"observaciones"=>$row['column_values'][11]['text'],"quien"=>$row['column_values'][12]['text']],
                                $row['column_values'][13]['title'] => ["criterio"=>$row['column_values'][13]['text'],"observaciones"=>$row['column_values'][14]['text'],"quien"=>$row['column_values'][15]['text']],
                                $row['column_values'][16]['title'] => ["criterio"=>$row['column_values'][16]['text'],"observaciones"=>$row['column_values'][17]['text'],"quien"=>$row['column_values'][18]['text']],
                                $row['column_values'][19]['title'] => ["criterio"=>$row['column_values'][19]['text'],"observaciones"=>$row['column_values'][20]['text'],"quien"=>$row['column_values'][21]['text']],
                                $row['column_values'][22]['title'] => ["criterio"=>$row['column_values'][22]['text'],"observaciones"=>$row['column_values'][23]['text'],"quien"=>$row['column_values'][24]['text']],
                                $row['column_values'][25]['title'] => ["criterio"=>$row['column_values'][25]['text'],"observaciones"=>$row['column_values'][26]['text'],"quien"=>$row['column_values'][27]['text']],
                                $row['column_values'][28]['title'] => ["criterio"=>$row['column_values'][28]['text'],"observaciones"=>$row['column_values'][29]['text'],"quien"=>$row['column_values'][30]['text']],
                                $row['column_values'][31]['title'] => ["criterio"=>$row['column_values'][31]['text'],"observaciones"=>$row['column_values'][32]['text'],"quien"=>$row['column_values'][33]['text']]]),
                            "evidence"=>$evidence,
                            "id_mon"=>$ids,
                            "calification"=> $cali."/"."100"
                            ];
                        $insert = DB::table('checklistfinop')->insert($ins);
                        if($insert){
                            $ens[] = $ids;
                            $changtext = 'mutation {change_simple_column_value (item_id:'.$ids.', board_id:4658499281, column_id:"texto", value: "'.$cali."/"."100".'") {id}}';
                            $chagesta = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4658499281, column_values: "{\"estado\" : {\"label\" : \"ENVIADO\"}}") {id}}';
                            $chan = $this->apimon($changtext);
                            $chans = $this->apimon($chagesta);
                            $inf = [
                                'fecha'=>$cated,
                                'puntuacion'=>$cali,
                                'admin'=>$admon,
                                'sucursal'=>$suc,
                                'tot1'=>$row['column_values'][4]['text'],
                                'obs1'=>$row['column_values'][5]['text'],
                                'per1'=>$row['column_values'][6]['text'],
                                'tot2'=>$row['column_values'][7]['text'],
                                'obs2'=>$row['column_values'][8]['text'],
                                'per2'=>$row['column_values'][9]['text'],
                                'tot3'=>$row['column_values'][10]['text'],
                                'obs3'=>$row['column_values'][11]['text'],
                                'per3'=>$row['column_values'][12]['text'],
                                'tot4'=>$row['column_values'][13]['text'],
                                'obs4'=>$row['column_values'][14]['text'],
                                'per4'=>$row['column_values'][15]['text'],
                                'tot5'=>$row['column_values'][16]['text'],
                                'obs5'=>$row['column_values'][17]['text'],
                                'per5'=>$row['column_values'][18]['text'],
                                'tot6'=>$row['column_values'][19]['text'],
                                'obs6'=>$row['column_values'][20]['text'],
                                'per6'=>$row['column_values'][21]['text'],
                                'tot7'=>$row['column_values'][22]['text'],
                                'obs7'=>$row['column_values'][23]['text'],
                                'per7'=>$row['column_values'][24]['text'],
                                'tot8'=>$row['column_values'][25]['text'],
                                'obs8'=>$row['column_values'][26]['text'],
                                'per8'=>$row['column_values'][27]['text'],
                                'tot9'=>$row['column_values'][28]['text'],
                                'obs9'=>$row['column_values'][29]['text'],
                                'per9'=>$row['column_values'][30]['text'],
                                'tot10'=>$row['column_values'][31]['text'],
                                'obs10'=>$row['column_values'][32]['text'],
                                'per10'=>$row['column_values'][33]['text']
                            ];
                            $pdf = $this->finop($inf);
                            if($pdf['msg'] == "enviado"){
                                //  $data = $pdf['archivo'];
                                //  $document = file_get_contents($data);
                                //  $doc = base64_encode($document);
                                 $chagemsg = 'mutation {change_multiple_column_values(item_id:'.$ids.', board_id:4658499281, column_values: "{\"estado5\" : {\"label\" : \"ENVIADO\"}}") {id}}';
                                 $chanms = $this->apimon($chagemsg);
                                //  $addfile = 'mutation ($file:file!) { add_file_to_column (item_id: '.$ids.', column_id: "archivo3", file: '.$doc.') {id}}';
                                // $chanfile = $this->apimonfile($addfile); //en algun moento funcionara
                                }
                                // return $chanfile;
                        }
                    }else{
                        $fail[]="El admon ".$admon." no existe";
                    }
                }else{
                    $fail[]="la sucursal ".$suc." no existe";
                }
            }
        }
        return response()->json("REGISTROS INSERTADOS = ".count($ens));//recorremos arreglos hasta los que ocuparemo
    }else {
        return response()->json("No hay registros");
    }
  }

  public function calificacionfinop($cali){
    $pg1 = $cali['pg1'] == "CUMPLE" ? 10 : 0;
    $pg2 = $cali['pg2'] == "CUMPLE" ? 10 : 0;
    $pg3 = $cali['pg3'] == "CUMPLE" ? 10 : 0;
    $pg4 = $cali['pg4'] == "CUMPLE" ? 10 : 0;
    $pg5 = $cali['pg5'] == "CUMPLE" ? 10 : 0;
    $pg6 = $cali['pg6'] == "CUMPLE" ? 10 : 0;
    $pg7 = $cali['pg7'] == "CUMPLE" ? 10 : 0;
    $pg8 = $cali['pg8'] == "CUMPLE" ? 10 : 0;
    $pg9 = $cali['pg9'] == "CUMPLE" ? 10 : 0;
    $pg10 = $cali['pg10'] == "CUMPLE" ? 10 : 0;

    $TOTAL =  $pg1 + $pg2 + $pg3 + $pg4 + $pg5 + $pg6 + $pg7 + $pg8 + $pg9 + $pg10;


    return $TOTAL;
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
}



//mx100-cedis-mkrqpwcczk.dynamic-m.com:1025/Assist/public/api/webhook

