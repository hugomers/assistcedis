<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\Moduls;
use App\Models\Area;
use App\Models\Log;
use App\Models\TypeRol;
use App\Models\User;
use App\Models\ModulesApp;
use App\Models\UserRol;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;


class UserController extends Controller
{
    public function createUser(Request $request){
        $nick = $request->nick;
        $pass = $request->pass;
        $rol = $request->_rol;
        $staff = $request->_staff;
        $store = $request->_store;
        $user = new User();
        $user->nick = $nick;
        $user->password = Hash::make($pass);
        $user->_staff = $staff;
        $user->_rol = $rol;
        $user->_store = $store;
        $user->save();
        return response()->json($user);
    }

    public function createMasiveUser(Request $request){
        $users = $request->users;
        foreach($users as $user){
            $nick = $user['nick'];
            $pass = $user['pass'];
            $rol = $user['rol'];
            $staff = $user['_staff'];
            $store = $user['_store'];
            $user = new User();
            $user->nick = $nick;
            $user->password = Hash::make($pass);
            $user->_staff = $staff;
            $user->_rol = $rol;
            $user->_store = $store;
            $user->save();
        }
        return response('Usuarios Creados',200);
    }

    // public function trySignin(Request $request){
    //     $nick = $request->nick; // recibe el nick
    //     $pass = $request->pass; // recibe el pass
    //     $user = User::with(['rol','store'])->where('nick',$nick)->first();

    //     if(($nick&&$pass)&&$user&&Hash::check($pass,$user->password)){ // comparacion de contraseña y carga de datos para la cuenta
    //             $datafortoken = ["uid"=>$user->id, "complete_name"=>$user->staff['complete_name'], "rol" => $user->rol['alias']];
    //             $token = $this->genToken($datafortoken);
    //             return $token;
    //             return response()->json([
    //                 "id"=>$user->staff['id'],
    //                 "name"=>$user->staff['complete_name'],
    //                 "credentials"=>$user,
    //                 "store"=>$user->store,
    //                 "rol"=>$user->rol['alias']
    //                 ,"token"=>$token
    //             ]);
    //     } return response("credenciales erroneas!", 404);// password incorrecto
    // }

    public function trySignin(Request $request){
        $request->validate([
            'nick' => 'required|string',
            'pass' => 'required|string',
        ]);

        $credentials = [
            'nick' => $request->nick,
            'password' => $request->pass,
        ];

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 404);
        }

        $user = Auth::guard('api')->user()->load(['stores.store','store', 'rol.modules']);
        if($user){
            Log::create([
              "_module"=>15,
              "_user"=>$user->id,
              "_type"=>4,
              "details"=>json_encode([
                "Nombre"=>$user->name,
                "alias"=>$user->nick,
                "Tipo"=>"Inicio Sesion",
                "ip"=>$request->ip()
              ])
            ]);}

        return response()->json([
            'credentials' => $user,
            'store' => $user->store,
            'rol' => $user->rol,
            'stores'=>$user->stores,
            'token' => $token
        ]);
    }

    public function changeStore(Request $request){
        $request->validate([
            'store' => 'required|integer'
        ]);

        $user = auth()->user();
        $store = $request->store;

        $token = JWTAuth::claims([
            'uid'    => $user->id,
            'name'   => $user->name,
            '_store' => $store,
            'rol'    => $user->rol->id
        ])->fromUser($user);

        return response()->json([
            'token' => $token,
            'store' => $store
        ]);
    }

    private function genToken($data){
        $data["ini"] = Carbon::now();
        $data["exp"] = Carbon::now()->add(3,"day");
        return Crypt::encryptString(json_encode($data));
    }

    public function getResources($uid){
        $user = User::with([
            'stores.store',
            'rol.modules' => function($q) {
                $q->orderBy('_modules', 'asc');
            },
            'rol.modules.module._modul'
        ])->find($uid);
        $groupedModules = [];

        foreach ($user->rol->modules as $rolModule) {
            $module = $rolModule->module;

            if ($module && $module->_modul) {
                $modulId = $module->_modul->id;
                if (!isset($groupedModules[$modulId])) {
                    $groupedModules[$modulId] = $module->_modul->toArray();
                    $groupedModules[$modulId]['modules'] = [];
                }

                $groupedModules[$modulId]['modules'][] = $module;
            }
        }

        $user->grouped_modules = array_values($groupedModules);
                return response()->json($user);

    }

    public function changeAvatar(Request $request){
        $user = User::find($request->id);
        $user->avatar = $request->avatar;
        $user->save();
        $res = $user->fresh();
        return response()->json($res);
    }

    public function getAreas(Request $request){
        $res = [
            "types"=>TypeRol::all(),
            "areas"=>Area::with(['roles.type','roles.modules'])->get(),
            "modules"=>ModulesApp::with('children.children')->where('deep',0)->get()
        ];
        return response()->json($res,200);
    }

    public function addArea(Request $request){
        $area = Area::create($request->all());
        $res = $area->fresh('roles.type')->toArray();
        if($res){
            $details = [
                "Nombre"=>$request->name,
                "Tipo"=>"Creacion de Area"
            ];
            $this->createLog(92,$request->uid(),1,$details);
        }
        return response()->json($res,200);
    }

    public function addRol(Request $request){
        $rol = $request->rol;
        $permissions = $request->permissions;
        $insCreate = UserRol::create($rol);
        if($insCreate){
            $insCreate->modules()->sync($permissions);
            $details = [
                "Nombre"=>$request->rol['name'],
                "Tipo"=>"Creacion de Puesto",
                "Permisos"=>$permissions
            ];
            $this->createLog(91,$request->uid(),1,$details);
            return response()->json([
                'message' => 'Rol creado correctamente',
                'rol' => $insCreate->load('modules','type')
            ], 201);
        }else{
            return response()->json('No se creo el puesto',500);
        }
    }


    public function modifyRol(Request $request){
        $rolData = $request->rol;
        $permissions = $request->permissions;
        $rol = UserRol::find($rolData['id']);
        if (!$rol) {
            return response()->json('Rol no encontrado', 404);
        }
        $rol->update($rolData);
        $rol->modules()->sync($permissions);
        $details = [
            "Nombre"   => $rol->name,
            "Tipo"     => "Cambio Puesto",
            "Permisos" => $permissions
        ];
        $this->createLog(91, $request->uid(), 2, $details);
        return response()->json([
            'message' => 'Rol actualizado correctamente',
            'rol' => $rol->load('modules', 'type')
        ], 200);
    }

    public function createLog($mod,$usr,$type,$details){
        $createLog = Log::create([
            "_module"=>$mod,
            "_user"=>$usr,
            "_type"=>$type,
            "details"=>json_encode($details)
        ]);
        return $createLog;
    }
}
