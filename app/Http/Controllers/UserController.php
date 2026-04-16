<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\Moduls;
use App\Models\User;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class UserController extends Controller
{
    public function trySignin(Request $request){
        $nick = $request->nick; // recibe el nick
        $pass = $request->pass; // recibe el pass
        $user = User::with(['store','rol.modules','rol.area','zone.stores','stores.store'])->where('nick',$nick)->first();

        if(($nick&&$pass)&&$user&&Hash::check($pass,$user->password)){ // comparacion de contraseña y carga de datos para la cuenta
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
        } return response("credenciales erroneas!", 404);// password incorrecto
    }

    private function genToken($data){
        $data["ini"] = Carbon::now();
        $data["exp"] = Carbon::now()->add(3,"day");
        return Crypt::encryptString(json_encode($data));
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
}
