<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
  public function __construct(){

  }


  public function replystaff(){
    $upins = [];
    $fail = [];

    $token = 'eyJhbGciOiJIUzI1NiJ9.eyJ0aWQiOjIwMTgwNzYyMCwidWlkIjoyMDY1ODc3OSwiaWFkIjoiMjAyMi0xMS0yOVQxODoyMToxMS4wMDBaIiwicGVyIjoibWU6d3JpdGUiLCJhY3RpZCI6ODM5Njc1MywicmduIjoidXNlMSJ9.nLLLRUTqG86usf18jqEYHIzf62rYA8Lee2coEEyTxlI';
    $apiUrl = 'https://api.monday.com/v2';
    $headers = ['Content-Type: application/json', 'Authorization: ' . $token];
    $query = 'query {
      items_by_column_values (board_id: 1520861792, column_id: "estatus", column_value: "ACTIVO") {
        name,
        column_values  {
          id
          title
          text
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
    $row = $style['data']['items_by_column_values'];
    foreach($row as $rows){
      $user = $rows['name'];
      $rcid = $rows['column_values'][0]['text'];
      $bus = DB::table('staff')->where('complete_name',$user)->where('id_rc',$rcid)->first();
      if($bus == null){
          if($rcid == ""){
              $fail[]="el colaborador ".$user." aun no tiene id";
          }else{
              $upsert = DB::table('staff')->upsert(['complete_name'=>$user,'id_rc'=>$rcid],['complete_name'],['id_rc']);
              $upins[] ="Usuario ".$user." insertado o actualizado";
          }
      }
    }
    $res = [
      "registros"=>count($upins),
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

}
