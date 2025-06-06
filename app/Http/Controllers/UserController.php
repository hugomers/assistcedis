<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\User;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class UserController extends Controller
{
    public function createUser(Request $request){
        $nick = $request->nick;
        $pass = $request->pass;
        $rol = $request->_rol;
        $staff = $request->_staff;

        $user = new User();
        $user->nick = $nick;
        $user->password = Hash::make($pass);
        $user->_staff = $staff;
        $user->_rol = $rol;
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

            $user = new User();
            $user->nick = $nick;
            $user->password = Hash::make($pass);
            $user->_staff = $staff;
            $user->_rol = $rol;
            $user->save();
        }
        return response('Usuarios Creados',200);
    }

    public function trySignin(Request $request){
        $nick = $request->nick; // recibe el nick
        $pass = $request->pass; // recibe el pass
        $user = User::with(['staff','store','rol',])->where('nick',$nick)->first();

        if(($nick&&$pass)&&$user&&Hash::check($pass,$user->password)){ // comparacion de contraseña y carga de datos para la cuenta
                $datafortoken = ["uid"=>$user->id, "complete_name"=>$user->staff['complete_name'] ];
                $token = $this->genToken($datafortoken);
                return response()->json([
                    "id"=>$user->staff['id'],
                    "name"=>$user->staff['complete_name'],
                    "credentials"=>$user,
                    "store"=>$user->store,
                    "rol"=>$user->rol['alias']
                    ,"token"=>$token
                ]);
        } return response("credenciales erroneas!", 404);// password incorrecto
    }

    private function genToken($data){
        $data["ini"] = Carbon::now();
        $data["exp"] = Carbon::now()->add(3,"day");
        return Crypt::encryptString(json_encode($data));
    }

    public function getResources($uid){
        $user = User::with([
            'stores.store',
            'rol.modules' => function($q) {$q->orderBy('_modules','asc');},
            'rol.modules.module'])->where('id',$uid)->first();
        return response()->json($user);

    }
}
