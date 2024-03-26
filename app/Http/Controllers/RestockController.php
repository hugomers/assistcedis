<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use Illuminate\Support\Facades\Http;

class RestockController extends Controller
{
    public function getSupply(){
        $staff = Staff::whereIn('_store',[1,2])->whereIn('_position',[2,46])->where('acitve',1)->get();
        return $staff;
    }

    public function saveSupply(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $surtidores = $request->supplyer;
        foreach($surtidores as $surtidor){
            $newres = new Restock;
            $supply = $surtidor['id'];
            $newres->_staff = $supply;
            $newres->_requisition = $pedido;
            $newres->_status = $status;
            $newres->save();
            $newres->fresh()->toArray();
        }

        return response()->json($newres,200);
    }

    public function saveVerified(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $verificador = $request->supplyer;
        $supply = $verificador;
        $newres = new Restock;
        $newres->_staff = $supply['id'];
        $newres->_requisition = $pedido;
        $newres->_status = $status;
        $newres->save();
        $newres->fresh()->toArray();
        return response()->json($newres,200);
    }

    public function getVerified(){
        $staff = Staff::whereIn('_store',[1,2])->whereIn('_position',[6,5,28,4])->where('acitve',1)->get();
        return $staff;
    }

    public function saveChofi(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $verificador = $request->supplyer;
        $supply = $verificador;
        $newres = new Restock;
        $newres->_staff = $supply['id'];
        $newres->_requisition = $pedido;
        $newres->_status = $status;
        $newres->save();
        $newres->fresh()->toArray();
        return response()->json($newres,200);
    }


    public function getChof(){
        $staff = Staff::whereIn('_store',[1,2])->whereIn('_position',[3])->where('acitve',1)->get();

        return $staff;
    }

    public function getCheck($cli){
        $store = Stores::with('Staff')->where('_client',$cli)->value('id');
        $staff = Staff::where('_store',$store,)->whereIn('_position',[7,8,16,17])->get();

        return $staff;
    }

    public function saveCheck(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $verificador = $request->supplyer;
        $supply = $verificador;
        $newres = new Restock;
        $newres->_staff = $supply['id'];
        $newres->_requisition = $pedido;
        $newres->_status = $status;
        $newres->save();
        $newres->fresh()->toArray();
        return response()->json($newres,200);
    }

    public function getSalida(Request $request){
        $salida = $request->all();
        $stores = Stores::find(1);
        $ip = $stores->ip_address;
        // $ip = '192.168.10.112:1619';
        $getdev = Http::post($ip.'/storetools/public/api/Resources/returnFac',$salida);
        if($getdev->status() != 200){
            return false;
        }else{
            return $getdev;
        }

    }
}
