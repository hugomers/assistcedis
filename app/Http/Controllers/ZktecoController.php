<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rats\Zkteco\Lib\ZKTeco;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\StaffController;

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
        $fail = [
            "conexion"=>[],
            "asistencia"=>[]
        ];

        $staff = new StaffController();
        $reply = $staff->replystaff();
        $registros = $reply;
        $employes = DB::table('staff')->select('id_rc')->get();
        foreach($employes as $employe){
            $sta[] = $employe->id_rc;
        }

        $devices = DB::table('assist_devices')->get();

        foreach($devices as $device){
            $sucursal = $device->_store;
            $dispositivo = $device->id;
            $ipaddress = $device->ip_address;
            $maxuid = DB::table('assist')->selectraw('MAX(auid) as max')->where('_device',$dispositivo)->value('max');

            $zk = new ZKTeco($ipaddress);
            if($zk->connect()){
                $checker[] = $zk->getAttendance();

                $assists = array_filter($checker,function($element) use ($maxuid){
                    return isset($element['uid']) && $element['uid'] > $maxuid;
                });
                $assists = array_filter($assists, function($element) use($sta){
                    return isset($element['id']) && in_array($element['id'],$sta);
                });
                    $goals[] = ["sucursal"=>$device->nick_name,"registros"=>count($assists)];
                    foreach($assists as $assist){
                        $user = DB::table('staff')->where('id_rc',$assist['id'])->value('id');
                        $report = [
                            "auid" => $assist['uid'],//id checada checador
                            "register" => $assist['timestamp'], //horario
                            "_staff" => $user,//id del usuario
                            "_store"=> $sucursal,
                            "_types"=>$assist['type'],//entrada y salida
                            "_class"=>$assist['state'],
                            "_device"=>$dispositivo,
                        ];
                        $insert = DB::table('assist')->insert($report);
                    }
            }else{
                $fail['conexion'][]= "El dispositivo de la sucursal ".$device->nick_name." no tiene conexion";
            }
        }
        $res = [
            "goals"=>$goals,
            "fail"=>$fail,
            // "registros_act"=>$registros
        ];

        return response()->json($res);
    }

    public function insturn(Request $request){
        $assist = $request->all();

        foreach($assist as $row){
            $res = $row['id'];
            $user = DB::table('staff')->where('id_rc',$res)->value('id');
            if($user){
                $ins = [
                    "_week"=>$row['semana'],
                    "_staff"=>$user,
                    "hour_hand"=>$row['turno']
                ];
                $insert = DB::table('assist_turn')->insert($ins);


            }else{
                $fail[]="El id ".$res." no existe";
            }

        }
        return $insert;
    }

    public function completeReport(){
        $goals = [];
        $fails = [];
        $deviceszkt = DB::table('assist_devices')->select('id','nick_name','ip_address','_store')->get();
        foreach($deviceszkt as $device){
            $zk = new ZKTeco($device->ip_address);
            if($zk->connect()){
                // if($zk->clearAttendance()){
                //     $goals[]  = ["sucursal"=>$device->nick_name];
                // }
                $assists = $zk->getAttendance();
                if($assists){
                    foreach($assists as $assist){
                        $auid = DB::table('assist')->where('auid',$assist['uid'])->where('_store',$device->_store)->first();
                        if(is_null($auid)){
                            $user = DB::table('staff')->where('id_rc',intval($assist['id']))->value('id');
                            if($user){
                                $report = [
                                    "auid" => $assist['uid'],//id checada checador
                                    "register" => $assist['timestamp'], //horario
                                    "_staff" => $user,//id del usuario
                                    "_store"=> $device->_store,
                                    "_types"=>$assist['type'],//entrada y salida
                                    "_class"=>$assist['state'],
                                    "_device"=>$device->id,
                                ];
                                $insert = DB::table('assist')->insert($report);
                            }
                        }
                    }
                    $goals[] =[
                        "id"=>$device->id,
                        "sucursal"=>$device->nick_name,
                        "conexion"=>"Conectado",
                        "registroes"=>count($assists)
                    ];
                }
            }
            else{
                $goals[] =[
                    "id"=>$device->id,
                    "sucursal"=>$device->nick_name,
                    "conexion"=>"SIN CONEXION",
                    "registroes"=>0
                ];
            }
        }


        return response()->json($goals,200);
    }

    public function delete (){
        $goals = [];
        $deviceszkt = DB::table('assist_devices')->select('id','nick_name','ip_address','_store')->get();
        foreach($deviceszkt as $device){
            $zk = new ZKTeco($device->ip_address);
            if($zk->connect()){
                if($zk->clearAttendance()){
                    $goals[] =[
                        "id"=>$device->id,
                        "sucursal"=>$device->nick_name,
                        "delete"=>"OK"
                    ];
                }
            }else{
                $goals[] =[
                    "id"=>$device->id,
                    "sucursal"=>$device->nick_name,
                    "delete"=>"SIN CONEXION"
                ];
            }
        }
        return response()->json($goals,200);
    }
}
