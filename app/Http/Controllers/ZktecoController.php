<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rats\Zkteco\Lib\ZKTeco;
use Illuminate\Support\Facades\DB;

class ZktecoController extends Controller
{
    public function pings(){
        $goal = [];
        $fail = [];
        $stores = DB::table('assist_devices')->get();
        foreach($stores as $store){
            $zk = new ZKTeco($store->ip_address);
            if($zk->connect()){
                $goal[] = $store->nick_name;
            }else{
                $fail[] = $store->nick_name;
            }
        }
        $res = [
            "fail"=>$fail,
            "goal"=>$goal
        ];
        return response()->json($res);
    }

    public function add(Request $request){
        $zk = new ZKTeco($request->ip);
        $store = DB::table('stores')->where('alias',$request->store)->value('id');
        if($zk->connect()){
            if($store){
                $ip = DB::table('assist_devices')->where('ip_address',$request->ip)->first();
                if($ip == false){
                        $serie = ltrim(stristr($zk->serialNumber(),'='),'=');
                        $dev = DB::table('assist_devices')->where('serial_number',$serie)->first();
                        if($dev == false){
                            $name = ltrim(stristr($zk->deviceName(),'='),'=');
                            $device = [
                                "name"=>$name,
                                "nick_name"=>$request->nick,
                                "serial_number"=>$serie,
                                "_store"=>$store,
                                "ip_address"=>$request->ip
                            ];
                            $insert = DB::table('assist_devices')->insert($device);
                            return response()->json(["msg"=>"insertado correctamente","dispositivo"=>$device],201);
                        }else{return response()->json("El numero de serie ya existe en el registro de el checador ".$dev->nick_name,404);}

                }else{return response()->json("La direccion IP ya se encuentra registrada en el reloj checador ".$ip->nick_name,404);}
            }else{return response()->json("No existe ninguna sucursal con el alias ".$request->store,404);}
        }else{return response()->json("No hay conexion a el checador",501);}
    }

    public function maxuaid(){
        $fail = [];
        $goal = [];

        $devices = DB::table('assist_devices')->get();

        foreach($devices as $device){
            $zk = new ZKTeco($device->ip_address);
            if($zk->connect()){
                $assist = $zk->getAttendance();
                if($assist){
                    $goal [] = ["sucursal"=>$device->nick_name,"uida"=>$assist];
                }else{$fail[]= $device->nick_name." No tiene checadas";}
            }else{$fail[] = $device->nick_name;}
        }
        $res = [
            "fail"=>$fail,
            "goal"=>$goal
        ];
        return $res;
    }

    public function Report(){
        $goals = [];
        $report = [];
        $fail = [];
        $ret = [];
        $devices = DB::table('assist_devices')->get();
        foreach($devices as $device){
            $zkteco = $device->ip_address;
            $zk = new ZKTeco($zkteco);
            if($zk->connect()){
                $assists = $zk->getAttendance();
                if($assists){
                    $serie = ltrim(stristr($zk->serialNumber(),'='),'=');
                    $sucursal = DB::table('assist_devices')->where('serial_number',$serie)->first();
                    if($sucursal){
                        foreach($assists as $assist){
                            $auid = DB::table('assist')->where('auid',$assist['uid'])->where('_store',$sucursal->_store)->first();
                            if(is_null($auid)){
                                $user = DB::table('staff')->where('id_rc',intval($assist['id']))->value('id');
                                if($user){
                                    $report = [
                                    "auid" => $assist['uid'],//id checada checador
                                    "register" => $assist['timestamp'], //horario
                                    "_staff" => $user,//id del usuario
                                    "_store"=> $sucursal->_store,
                                    "_types"=>$assist['type'],//entrada y salida
                                    "_class"=>$assist['state'],
                                    "_device"=>$sucursal->id,
                                    ];
                                    $insert = DB::table('assist')->insert($report);
                                    $ret[] = $report;
                                }else{$fail[]= "El id ".$assist['id']." no tiene usuario registro ".$assist['timestamp'];}
                            }
                        }
                    }else{$fail[]=$device->nick_name." La Sucursal no existe la serie".$serie;}
                    $goals [] = [ "sucursal"=>$device->nick_name ,"registros"=>count($ret), "regis"=>$ret, "fail"=>$fail];
                }else{$fail [] = $device->nick_name." No hay registros por el momento";}
            }else{$fail [] = $device->nick_name." No hay conexion a el checador";}
        }
        $res = [
            "goal"=>$goals,
            "fail"=>$fail
        ];
        return response()->json($res);
    }
}
