<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssistDevice;
use App\Models\UserRol;
use App\Models\Assist;
use App\Models\Fechas;
use App\Models\Stores;
use App\Models\AssistJustification;
use App\Models\JustificationState;
use App\Models\JustificationType;
use App\Models\PaymentPercentage;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Rats\Zkteco\Lib\ZKTeco;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Carbon\Carbon;


class AssistController extends Controller
{
    public function getReport(Request $request){
        $rol = $request->rid;
        $userRol = UserRol::find($rol);
        $store = $request->sid;
        $user = $request->uid;
        $zone = $request->zone;
        $stores = Stores::where('_active',1);
        if(($request->filled('ranges'))){
            $yearandweek = (object) $request->ranges;
        }else{
            $currentDate = Fechas::whereDate('fecha', now())->first();
            $yearandweek = (object)[
                '_year' => $currentDate->_year,
                'start_week' => $currentDate->_week,
                'end_week' => $currentDate->_week
            ];
        }

        $data = collect(DB::select("CALL report_assist(?, ?, ?)", [ $yearandweek->_year,  $yearandweek->start_week, $yearandweek->end_week ]));
        $data = $data->map(function ($item) {
        if ($item->justifications) {
            $item->status = $item->justifications;
        } elseif (!$item->register_time) {
            if (Carbon::parse($item->fecha)->dayOfWeek === Carbon::SUNDAY) {
                $item->status = 'DESCANSO';
            } else {
                $item->status = 'FALTA';
            }
        } elseif ($item->register_time > $item->turno) {
            $item->status = $item->register_time . ' R';
        } else {
            $item->status = $item->register_time;
        }

        return $item;
        });
        $grouped = $data->groupBy(function ($item) {
            return $item->id . '-' . $item->_week;
        });

        $report = $grouped->map(function ($days) {
            $first = $days->first();
            $row = [
                'year' => $first->_year,
                'week' => $first->_week,
                'employee_id' => $first->id,
                'employee' => $first->complete_name,
                'store' => $first->name,
                '_store' => $first->_store,
                'turn' => $first->turno,
                'lunes' => null,
                'martes' => null,
                'miercoles' => null,
                'jueves' => null,
                'viernes' => null,
                'sabado' => null,
                'domingo' => null,
                'vacaciones'=>0,
                'faltas'=>0,
                'retardos'=>0,
            ];
            foreach ($days as $day) {
                $dayName = Carbon::parse($day->fecha)
                    ->locale('es')
                    ->dayName;
                $dayName = str_replace(
                    ['miércoles', 'sábado'],
                    ['miercoles', 'sabado'],
                    strtolower($dayName)
                );
                $row[$dayName] = $day->status;
                if (str_contains($day->status, 'VACACIONES')) {
                    $row['vacaciones']++;
                }

                if ($day->status === 'FALTA') {
                    $row['faltas'] += 1;

                } elseif (str_contains($day->status, '-0%')) {
                    $row['faltas'] += 1;

                } elseif (str_contains($day->status, '-50%')) {
                    $row['faltas'] += 0.5;
                }

                if (str_contains($day->status, ' R')) {
                    $row['retardos']++;
                }
            }
            return $row;
        })->values();
        if($userRol->_type == 2){
            $stpres = $stores->where('id',$store);
            $report = $report->where('_store',$store)->values();
        }
        if($zone){
            $stores = $stores->whereIn('id',$zone);
            $report = $report->whereIn('_store',$zone)->values();
        }
        $res = [
            "report"=>$report,
            "dates"=>Fechas::orderBy('fecha','asc')->get(),
            "devices"=>$stores->get()
        ];

        return response()->json($res) ;
    }

    public function getDevices(Request $request){
        $rol = $request->rid;
        $store = $request->sid;
        $user = $request->uid;
        $zone = $request->zone;
        $userRol = UserRol::find($rol);
        $device = AssistDevice::with('store');
        if($userRol->_type == 2){
            $device = $device->where('_store',$store);
        }
        if($zone){
            $device = $device->whereIn('_store',$zone);
        }
        return response()->json($device->get());

    }

    public function ping($d){
        $device = AssistDevice::find($d);
        $zk = new ZKTeco($device->ip_address);

        if($zk->connect()){
            $date = $zk->getTime();
            $current = date('Y-m-d H:i:s');
            $register = $zk->getAttendance();
            $res = [
                "connect"=>true,
                "date"=>$date,
                "register"=>count($register),
                "current" => $current
            ];
            $zk->disconnect();
            return response()->json($res,200);
        }else{
            $res = [
                "connect"=>false,
                "date"=>'Sin Conexion',
                "register"=>'Sin Conexion',
                "current"=>"Sin Conexion"
            ];

            return response()->json($res,200);
        }

    }

    public function getRegisDevice($d){
        $goals = [];
        $fails = [];
        $report = [];
        $device = AssistDevice::find($d);
        $zk = new ZKTeco($device->ip_address);
        $exist = Assist::with('user')->where('_device',$device->id)->get()->toArray();
        $rexist  = array_map(function($val)

        {
            return [
                'auid'=>$val['auid'],
                'id'=>$val['user']['id_rc'],
                'state'=>$val['_class'],
                'timestamp'=>$val['register'],
                'type'=>$val['_type']
            ];
        }
                ,$exist);
        if($zk->connect()){
             $assists = $zk->getAttendance();
            if($assists){
                $dev = array_map(function($val){return implode(',',$val);},$assists);
                $dba = array_map(function($val){return implode(',',(array)$val);},$rexist);
                $diff = array_diff($dev, $dba);
                $vdiff = array_values($diff);
                $diferencias = array_map(function($val){ return explode(',',$val);} ,$vdiff);
                // return $diferencias;
                if($diferencias){
                    foreach($diferencias as $assist){
                        $user = User::where('id_rc',$assist[1])->first();
                        if($user){
                            $report [] = [
                                "auid" => $assist[0],//id checada checador
                                "register" => $assist[3], //horario
                                "_user" => $user->id,//id del usuario
                                "_store"=> $device->_store,
                                "_type"=>$assist[4],//entrada y salida
                                "_class"=>$assist[2],//condedo o contrasena
                                "_device"=>$device->id,
                            ];
                        }else{
                            // $finduser = $zk->getUser();
                            // $find = array_values(array_filter($finduser, function($val) use($assist){ return $val['userid'] == $assist[1];}));
                            // $fails[]=$device->nick_name." no existe el id ".$assist[1]." con el nombre ".$find[0]['name']." favor de revisar ";
                        }
                    }
                    $insert = Assist::insert($report);
                    if($insert){
                        $goals[] = $device->nick_name." se insertaron ".count($report)." registros";
                    }
                }else{
                    $goals[] = $device->nick_name." No hay registros";
                }
            }
            $zk->disconnect();
            $res = ["goals"=>$goals, "fails"=>$fails];
            return response()->json($res, 200);
        }else{
            return response()->json('Sin Conexion',200);
        }
    }

    public function changeDate($d){
        $device = AssistDevice::find($d);
        $zk = new ZKTeco($device->ip_address);
        if($zk->connect()){
            $date= date('Y-m-d H:i:s');
            $zk->setTime($date);
            $zk->disconnect();
            $res = [
                "change"=>true,
                "date"=>$date,
            ];
            return response()->json($res,200);
        }else{
            $res = [
                "change"=>false,
                "date"=>'Sin Conexion',
            ];
            return response()->json($res,401);
        }
    }

    public function deleteAttendance($d){
        $device = AssistDevice::find($d);
        $zk = new ZKTeco($device->ip_address);
        if($zk->connect()){
            $zk->clearAttendance();
            $zk->disconnect();
            $res = [
                "delete"=>true,
                "mssge"=>"Se eliminaron los registros"
            ];
            return response()->json($res,200);
        }else{
            $res = [
                "delete"=>false,
                "mssge"=>"No Se eliminaron los registros"
            ] ;
            return response()->json($res,401);
        }
    }

    public function Resourcesform(Request $request){
        $store = $request->sid;
        $zones = $request->zone;
        $uid = User::find($request->uid);
        $area = UserRol::with('area')->where('id',$uid->_rol)->first();
        if($area->alias == 'rrhh' || $area->alias == 'root'){
            $staff = User::with('rol')->where([['_state','!=',4]])->get();
        }else if ($zones) {
            $staff = User::with('rol')->where('_state','!=',4)->whereIn('_store',$zones)->get();
        }else{
            $staff = User::with('rol')->where([['_store',$store],['_state','!=',4]])->whereHas('rol', function($q) use($area) { $q->where('_area',$area->area['id']); })->get();
        }
        $types = JustificationType::all();
        // $RcIds = implode(',',$staff->pluck('id')->toArray());
        $justifications = AssistJustification::with('user','paymen','type','state','createdBy')
        ->where('evidence','!=','')
        ->whereIn('_user',$staff->pluck('id')->toArray())
        ->whereRaw('WEEK(( created_at - INTERVAL (DAYOFWEEK(created_at) % 7) DAY), 7) = WEEK((CURDATE() - INTERVAL (DAYOFWEEK(CURDATE()) % 7) DAY), 7)')
        ->whereRaw('YEAR(created_at) = YEAR((CURDATE() - INTERVAL (DAYOFWEEK(CURDATE()) % 7) DAY))')->get();
        foreach($justifications as $justification){
            $userId = $justification['_user'];
            $folderName = $justification['evidence'];
            $folderPath = "vhelpers/users/{$userId}/justifications/{$folderName}";
            $files = Storage::files($folderPath);
            $filesWithUrls = collect($files)->map(function ($path) {
                return [
                    'path' => $path,
                    // 'url' => Storage::temporaryUrl($path, now()->addMinutes(10))
                    'url' => Storage::Url($path)
                ];
            });
            $justification['files'] = $filesWithUrls;
        }
        $res = [
            'users'=>$staff,
            'states'=>JustificationState::all(),
            'types'=>$types,
            'payments'=>PaymentPercentage::all(),
            'justifications'=>$justifications
        ];
        return response()->json($res);
    }

    public function addForm(Request $request) {
        $jstf = $request->all();
        $justification = new AssistJustification;
        $justification->_user = $jstf['user'];
        $justification->_created_by = $jstf['_created_by'];
        $justification->start_date = $jstf['start_date'];
        $justification->final_date = $jstf['final_date'];
        $justification->_type = $jstf['_type'];
        $justification->_state = 2;
        $justification->notes = $jstf['notes'];
        if ($request->hasFile("evidence")) {
            $folderName = uniqid();
            $folderPath = 'vhelpers/users/'.$jstf['user'].'/justifications/'.$folderName;
            $files = $request->file("evidence");
            foreach ($files as $file) {
                $fileName = $file->getClientOriginalName();
                $route = Storage::put($folderPath . '/' . $fileName, file_get_contents($file));
            }
            $justification->evidence = $folderName;
        }
        $justification->save();
        $res = $justification->load(['user','paymen','type','state','createdBy']);
        $userId = $justification['_user'];
        $folderName = $justification['evidence'];
        $folderPath = "vhelpers/users/{$userId}/justifications/{$folderName}";
        $files = Storage::files($folderPath);
        $filesWithUrls = collect($files)->map(function ($path) {
            return [
                'path' => $path,
                'url' => Storage::Url($path)
            ];
        });
        $justification['files'] = $filesWithUrls;
        if($res){
            return response()->json($res,200);
        }else{
            return response()->json('No se pudo crear la justificacion',400);
        }
    }

    public function changeStatus(Request $request){
        $justification = AssistJustification::find($request->id);
        $justification->_type = $request->type['id'];
        $justification->_pay_percentage = $request->paymen['id'];
        $justification->_state = $request->state['id'];
        $justification->save();
        $res = $justification->load(['user','paymen','type','state','createdBy']);
        $userId = $justification['_user'];
        $folderName = $justification['evidence'];
        $folderPath = "vhelpers/users/{$userId}/justifications/{$folderName}";
        $files = Storage::files($folderPath);
        $filesWithUrls = collect($files)->map(function ($path) {
            return [
                'path' => $path,
                'url' => Storage::Url($path)
            ];
        });
        $justification['files'] = $filesWithUrls;
        return response()->json($res, 200);
    }

    public function getJustifications(Request $request){
        $store = $request->sid;
        $zones = $request->zone;
        $fechas = $request->date;
        if(isset($fechas['from'])){
            $from = $fechas['from'];
            $to = $fechas['to'];
        }else{
            $from = $fechas;
            $to = $fechas;
        }
        $uid = User::find($request->uid);
        $area = UserRol::with('area')->where('id',$uid->_rol)->first();
        if($area->alias == 'rrhh' || $area->alias == 'root'){
            $staff = User::with('rol')->where([['_state','!=',4]])->get();
        }else if ($zones) {
            $staff = User::with('rol')->where('_state','!=',4)->whereIn('_store',$zones)->get();
        }else{
            $staff = User::with('rol')->where([['_store',$store],['_state','!=',4]])->whereHas('rol', function($q) use($area) { $q->where('_area',$area->area['id']); })->get();
        }
        $justifications = AssistJustification::with('user','paymen','type','state','createdBy')
        ->where('evidence','!=','')
        ->whereIn('_user',$staff->pluck('id')->toArray())
        ->whereBetween('start_date', [$from, $to])
        ->get();
        foreach($justifications as $justification){
            $userId = $justification['_user'];
            $folderName = $justification['evidence'];
            $folderPath = "vhelpers/users/{$userId}/justifications/{$folderName}";
            $files = Storage::files($folderPath);
            $filesWithUrls = collect($files)->map(function ($path) {
                return [
                    'path' => $path,
                    'url' => Storage::Url($path)
                ];
            });
            $justification['files'] = $filesWithUrls;
        }
        return response()->json($justifications);
    }

    public function ReplyAssistAut(){
        $goals = [];
        $fails = [];
        $report = [];
        $devices = AssistDevice::with('store')->get();
        if(count($devices) > 0 ){
            foreach($devices as $device){
                $inicio = microtime(true);
                echo 'actualizando '.$device->nick_name." \n";
                $zk = new ZKTeco($device->ip);
                $exist = Assist::with('user')->where('_device',$device->id)->get()->toArray();
                $rexist  = array_map(function($val)
                { return [
                        'auid'=>$val['auid'],
                        'id'=>$val['user']['RC_id'],
                        'state'=>$val['_class'],
                        'timestamp'=>$val['register'],
                        'type'=>$val['_type']
                    ];
                },$exist);

                if($zk->connect()){
                     $assists = $zk->getAttendance();
                    if($assists){
                        $dev = array_map(function($val){return implode(',',$val);},$assists);
                        $dba = array_map(function($val){return implode(',',(array)$val);},$rexist);
                        $diff = array_diff($dev, $dba);
                        $vdiff = array_values($diff);
                        $diferencias = array_map(function($val){ return explode(',',$val);} ,$vdiff);
                        // return $diferencias;
                        if($diferencias){
                            foreach($diferencias as $assist){
                                $user = User::where('RC_id',$assist[1])->first();
                                if($user){
                                    $report [] = [
                                        "auid" => $assist['0'],//id checada checador
                                        "register" => $assist['3'], //horario
                                        "_user" => $user->id,//id del usuario
                                        "_store"=> $device->_store,
                                        "_type"=>$assist['4'],//entrada y salida
                                        "_class"=>$assist['2'],//condedo o contrasena
                                        "_device"=>$device->id,
                                    ];
                                }
                            }
                            $insert = Assist::insert($report);
                            // return $report;
                            if($insert){
                                $goals = $device->nick_name." se insertaron ".count($report)." registros";
                            }else{
                                $fails = $device->nick_name."no se insertaron ".count($report)." registros";
                            }
                        }else{
                            $goals = $device->nick_name." No hay registros";
                        }
                    }else{
                        $goals = $device->nick_name."No hay resgistros";
                    }
                    $termino = microtime(true);
                    $zk->disconnect();
                    $res = ["goals"=>$goals, "fails"=>$fails , "Dispositivo" => $device->nick_name, 'tiempo'=>round($termino-$inicio,2)];
                    echo json_encode($res)." \n";

                }else{
                    $termino = microtime(true);
                    $message = 'El dispositivo '.$device->nick_name.' no tiene conexion :('." \n";
                    $msg = $this->msg($message);
                    if($msg){
                        echo 'Mensaje Enviado'.' tiempo :'.round($termino-$inicio)." \n";
                    }else{
                        echo 'No se envio el mensaje'." \n";
                    }
                }
                $goals=[];
                $fails=[];
                $report=[];
            }

        }else{
            echo 'No hay Dispositivos brou';
        }
    }





}
