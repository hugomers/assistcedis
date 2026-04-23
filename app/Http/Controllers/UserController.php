<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\UserRol;
use App\Models\AccountVA;
use App\Models\Area;
use App\Models\UserMedia;
use App\Models\UserState;
use App\Models\ModulesApp;
use App\Models\Enterprise;
use App\Models\TypeRol;
use App\Models\Position;
use App\Models\Restock;
use App\Models\Moduls;
use App\Models\User;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class UserController extends Controller
{
    public function addUsser(Request $request){
        try {
            $res = DB::transaction(function () use ($request) {
                $user = $request->all();
                // $createdBy = $request->uid();
                $create = User::create([
                    "nick"            => $user['nick'],
                    "complete_name"   =>  $user['complete_name'],
                    "dob"             => $user['dob'],
                    "celphone"        => $user['celphone'],
                    "email"           => $user['email'],
                    "gender"          => $user['gender'],
                    "_rol"            => $user['rol'],
                    "_store"          => $user['stores']['principal']['id'],
                    "_state"          => 1,
                    "avatar"          => 'avatar1.png',
                    "change_password" => 1,
                    "password"        => Hash::make('12345')
                ]);
                if (count($user['stores']['val']) > 1) {
                    $create->stores()->sync($user['stores']['val']);
                }
                if ($request->hasFile('profile')) {
                    $avatar = $request->file('profile');
                    $fileName = $create->nick . '.' . $avatar->getClientOriginalExtension();

                    $userMedia = UserMedia::create([
                        "_user" => $create->id,
                        "_type" => 1,
                        "path"  => $fileName,
                        "mime"  => $avatar->getClientOriginalExtension(),
                    ]);

                    $folderPath = 'vhelpers/users/' . $create->id . '/' . $userMedia->id . '/' . $fileName;
                    Storage::put($folderPath, file_get_contents($avatar));
                }
            // $details = [
            //     "Usuario"=>$create,
            //     "Tipo"=>"Nuevo Usuario",
            //     "ip"=>$request->ip()
            // ];
            // $this->createLog(15, $createdBy, 1, $details);
                return $create->load(['rol.area','state','store','media','stores','enterprise']);
            });
            return response()->json($res, 201);
        } catch (\Throwable $e) {
            return response()->json([
                "message" => "No se logró crear el usuario",
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    public function resetpass(Request $request){
        $user = User::findOrFail($request->user);
        $user->update([
            "password"=>Hash::make('12345'),
            "change_password"=>1,
            "_state"=>5
        ]);
        // $details = [
        //     "id" => $user->id,
        //     "alias"=>$user->nick,
        //     "Tipo"=>"Reseteo de Contrasena",
        //     "ip"=>$request->ip()
        // ];
        // $this->createLog(15, $request->uid(), 2, $details);
        return response()->json($user);
    }


    public function modifyUser(Request $request){
        // $createdBy = $request->uid();
        $user = User::findOrFail($request->user);
        $data = $request->input('modify');
        $status = $data['_state'] ?? null;
        if (!array_key_exists('_state', $data)) {
            if ($user->_state == 2 && count($data) > 0) {
                $data['_state'] = 5; // reinicio
            }
        }
        $stores = $data['stores'] ?? null;
        unset($data['stores']);
        $user->update($data);
        if ($stores !== null){
            $user->stores()->detach();
            if(count($stores)>1) {
                $user->stores()->sync($stores);
            }
        }
        // $details = [
        //     "Usuario"=>$user,
        //     "Tipo"=>"Actualizacion Usuario",
        //     "modificacion"=>$data,
        //     "ip"=>$request->ip()
        // ];
        // $this->createLog(15, $createdBy, 2, $details);
        return response()->json([
            'ok' => true,
            'user' => $user->load(['rol.area','state','store','media','stores','enterprise'])
        ]);
    }

    public function trySignin(Request $request){
        $nick = $request->nick; // recibe el nick
        $pass = $request->pass; // recibe el pass
        if(!($nick&&$pass)){
            return response()->json(['message'=> 'Se Necesitan datos de Inicio de Sesion'],403);
        }
        $user = User::with(['store','rol.modules','rol.area','zone.stores','stores'])->where('nick',$nick)->first();
        if(!$user){
            return response()->json(['message'=> 'El Usuario No existe'],403);
        }

        if(!Hash::check($pass,$user->password)){
            return response()->json(['message'=> 'Credenciales Erroneas'],403);
        }
        if ($user->_state == 3) {
            return response()->json([
                'state' => 3,
                'error' => 'Usuario Bloqueado, Favor de acercarce a un encargado :0'
            ], 403);
        }
        if ($user->_state == 4) {
            return response()->json([
                'state' => 4,
                'error' => 'Usuario dado de Baja x_x'
            ], 403);
        }
        if($user->_state == 5){
            $usr = User::find($user->id);
            $usr->_state = 2;
            $usr->save();
            $user->_state = 2;
        }

        // if($user){
        //     $details = [
        //         "Nombre"=>$user->name,
        //         "alias"=>$user->nick,
        //         "Tipo"=>"Inicio Sesion",
        //         "ip"=>$request->ip()
        //     ];
        //     $this->createLog(15, $user->id, 4, $details);
        // }
        $datafortoken = ["uid"=>$user->id, "complete_name"=>$user->complete_name, "rol" => $user->rol['alias']];
        $token = $this->genToken($datafortoken);
        return response()->json([
            "id"=>$user->id,
            "name"=>$user->complete_name,
            "credentials"=>$user,
            "store"=>$user->store,
            "rol"=>$user->rol['alias']
            ,"token"=>$token
        ]);
    }

    public function chagePassword(Request $request){
        $pass =  $request->newpass;
        $user = User::find($request->uid);
        $user->change_password = 0;
        $user->password = Hash::make($pass);
        if($user->_state == 1){
            $user->_state = 2;
        }
        $user->save();
        // $details = [
        //     "id" => $user->id,
        //     "Nombre"=>$user->name,
        //     "alias"=>$user->nick,
        //     "Tipo"=>"Cambio Contrasena",
        //     "ip"=>$request->ip()
        // ];
        // $this->createLog(15, $request->uid(), 2, $details);
        return response()->json(["message"=>'Contrasena Actualizada'],201);
    }

    public function getUsers(Request $request){
        $rol = $request->rid;
        $store = $request->sid;
        $user = $request->uid;
        $userRol = UserRol::find($rol);
        $areas = Area::with('roles');
        $users = User::with(['rol.area','state','store','media','stores','enterprise']);
        $stores = Stores::where('_active',1);
        if($userRol->_type == 2){//si es operativo solo mostraras los usuarios de la sucursal activa
            $users = $users->where('_store',$store);
            $areas = $areas->wherehas('roles', function($q)use($rol){$q->where('id',$rol);});
            $stores = $stores->where('id',$store);
        }
        $res = [
            "rol"=>$userRol,
            "users"=>$users->get(),
            "stores"=>$stores->get(),
            "areas"=>$areas->get(),
            "states"=>UserState::all(),
            "enterprises"=>Enterprise::all(),
        ];
        return response()->json($res,200);
    }


    private function genToken($data){
        $data["ini"] = Carbon::now();
        $data["exp"] = Carbon::now()->add(3,"day")->endOfDay();;
        return Crypt::encryptString(json_encode($data));
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

    public function getResources($uid){
        $user = User::with([
            'zone.stores',
            'stores.store',
            'rol.modules'
        ])->find($uid);
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
        // if($res){
        //     $details = [
        //         "Nombre"=>$request->name,
        //         "Tipo"=>"Creacion de Area"
        //     ];
        //     $this->createLog(92,$request->uid(),1,$details);
        // }
        return response()->json($res,200);
    }

    public function addRol(Request $request){
        $rol = $request->rol;
        $permissions = $request->permissions;
        $insCreate = UserRol::create($rol);
        if($insCreate){
            $insCreate->modules()->sync($permissions);
            // $details = [
            //     "Nombre"=>$request->rol['name'],
            //     "Tipo"=>"Creacion de Puesto",
            //     "Permisos"=>$permissions
            // ];
            // $this->createLog(92,$request->uid(),1,$details);
            return response()->json([
                'message' => 'Rol creado correctamente',
                'rol' => $insCreate->load('modules','type')
            ], 201);
        }else{
            return response()->json('No se creo el puesto',500);
        }
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

    public function modifyRol(Request $request){
        $rolData = $request->rol;
        $permissions = $request->permissions;
        $rol = UserRol::find($rolData['id']);
        if (!$rol) {
            return response()->json('Rol no encontrado', 404);
        }
        $rol->update($rolData);
        $rol->modules()->sync($permissions);
        // $details = [
        //     "Nombre"   => $rol->name,
        //     "Tipo"     => "Cambio Puesto",
        //     "Permisos" => $permissions
        // ];
        // $this->createLog(92, $request->uid(), 2, $details);
        $change = User::where([['_rol',$rol->id],['_state',2]])->update([
            "_state"=>5
        ]);
        return response()->json([
            'message' => 'Rol actualizado correctamente',
            'rol' => $rol->load('modules', 'type')
        ], 200);
    }

    public function getUserWorkpoints(){
        return response()->json([
            "users"=>User::with(['store','rol.area','state','stores','media'])->where('_state','!=',4)->get(),
            "stores"=>Stores::where('_active',1)->get(),
            "areas"=>Area::with('roles')->get(),
            "states"=>UserState::all(),
        ],200);
    }
    public function changeWorkpoint(Request $request){
        // $createdBy = $request->uid();
        $user = User::findOrFail($request->user);
        $wpori = $user->_store;
        $data = $request->input('modify');
        if (!array_key_exists('_state', $data)) {
            if ($user->_state == 2 && count($data) > 0) {
                $data['_state'] = 5; // reinicio
            }
        }
        $stores = $data['stores'] ?? null;
        unset($data['stores']);
        $user->update($data);
        if ($stores !== null){
            $user->stores()->detach();
            if(count($stores)>1) {
                $user->stores()->sync($stores);
            }
        }
        // $details = [
        //     "Usuario"=>$user,
        //     "Tipo"=>"Cambio de Sucursal",
        //     "origen"=>$wpori,
        //     "destino"=>$user->_store,
        //     "modificacion"=>$data,
        //     "ip"=>$request->ip()
        // ];
        // $this->createLog(15, $createdBy, 2, $details);
        return response()->json([
            'ok' => true,
            'user' => $user->load('rol', 'state', 'store','media','stores','enterprise')
        ]);
    }


}
