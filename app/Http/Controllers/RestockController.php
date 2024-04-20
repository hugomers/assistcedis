<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RestockController extends Controller
{
    public function getSupply(){
        $staff = Staff::whereIn('_store',[1,2])->whereIn('_position',[2,46])->where('acitve',1)->get();
        return $staff;
    }

    // public function getSupplier(){
    //     $id = $request->id;
    //     $restock = Restock::where('_requisition', $id)->get();
    //     return $restock;
    // }

    public function saveSupply(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $surtidores = $request->supplyer;
        $products = DB::connection('vizapi')->table('product_required')->where('_requisition',$pedido)->get()->toArray();

        $asig = [];
        $num_productos = count($products);
        $num_surtidores = count($surtidores);
        $supplyper = floor($num_productos / $num_surtidores);
        $remainder = $num_productos % $num_surtidores;
        $counter = 0;

        foreach ($surtidores as $surtidor) {
            $asigpro = array_slice($products, $counter * $supplyper, $supplyper);

            if ($remainder > 0) {
                $asigpro = array_merge($asigpro, [$products[$num_productos - $remainder]]);
                $remainder--;
            }
            foreach($asigpro as $product){
                $upd = [
                    "_suplier"=>$surtidor['complete_name'],
                    "_suplier_id"=>$surtidor['id']
                ];
                $dbproduct = DB::connection('vizapi')
                ->table('product_required')
                ->where([['_requisition',$product->_requisition],['_product',$product->_product]])
                ->update($upd);
            }
            $asig[$surtidor['complete_name']] = $asigpro;

            $counter++;
        }

        foreach($surtidores as $surtidor){
            $newres = new Restock;
            $supply = $surtidor['id'];
            $newres->_staff = $supply;
            $newres->_requisition = $pedido;
            $newres->_status = $status;
            $newres->save();
            $newres->fresh()->toArray();

            $ins = [
                "_requisition"=>$pedido,
                "_suplier_id"=>$supply,
                "_suplier"=>$surtidor['complete_name'],
                "_status"=>$status
            ];

            $inspart = DB::connection('vizapi')->table('requisition_partitions')->insert($ins);


        }
        return response()->json($newres,200);
    }

    public function saveVerified(Request $request){
        $pedido = $request->pedido;
        $verificador = $request->verified;
        $supply = $request->surtidor;
        $change = DB::connection('vizapi')
        ->table('requisition_partitions')
        ->where([['_requisition',$pedido],['_suplier_id',$supply]])
        ->update(['_out_verified'=>$verificador]);


        // $newres = new Restock;
        // $newres->_staff = $supply['id'];
        // $newres->_requisition = $pedido;
        // $newres->_status = $status;
        // $newres->save();
        // $newres->fresh()->toArray();
        return response()->json($change,200);
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

    public function changeStatus(Request $request){
        $pedido = $request->id;
        $status = $request->state;
        $supply = $request->supply;

        $change = DB::connection('vizapi')
        ->table('requisition_partitions')
        ->where([['_requisition',$pedido],['_suplier_id',$supply]])
        ->update(['_status'=>$status]);

        if($change){
            return response()->json($request,200);
        }else{
            return response()->json($request,500);
        }
    }
}
