<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RestockController extends Controller
{
    public function getSupply(){
        $staff = Staff::whereIn('_store',[1])->whereIn('_position',[6,3,2,46])->where('acitve',1)->get();
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
        $products = DB::connection('vizapi')
        ->table('product_required AS PR')
        ->leftjoin('product_location AS PL','PL._product','PR._product')
        ->leftjoin('celler_section AS CS','CS.id','PL._location')
        ->leftJoin('celler AS C', function($join) {
            $join->on('C.id', '=', 'CS._celler')
                 ->where('C._workpoint', '=', 1);
        })
        ->select('PR._product','PR._requisition', DB::raw(' IFNULL(GROUP_CONCAT(CS.root), null) as locations'))
        ->where([['PR._requisition',$pedido]])
        ->groupBy('PR._requisition','PR._product')
        ->orderby(DB::raw(' IFNULL(GROUP_CONCAT(CS.root), null)'))
        ->get()
        ->toArray();
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

            $inspart = DB::connection('vizapi')->table('requisition_partitions')->insertGetId($ins);
            $updtable = DB::connection('vizapi')
            ->table('product_required')
            ->where([['_requisition',$pedido],['_suplier_id',$supply]])
            ->update(['_partition'=>$inspart]);
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
        $staff = Staff::whereIn('_store',[1,2])->whereIn('_position',[6,10,1])->where('acitve',1)->get();
        return $staff;
    }

    public function saveChofi(Request $request){
        $status = $request->status;
        $pedido = $request->pedido;
        $verificador = $request->supplyer;
        $chofer = $request->chofi;

        $change = DB::connection('vizapi')
        ->table('requisition_partitions')
        ->where([['_requisition',$pedido],['_suplier_id',$verificador]])
        ->update(['_driver'=>$chofer['id']]);


        $newres = new Restock;
        $newres->_staff = $chofer['id'];
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
        $verificador = $request->verified;
        $suplier = $request->supplyer;

        $change = DB::connection('vizapi')
        ->table('requisition_partitions')
        ->where([['_requisition',$pedido],['_suplier_id',$suplier]])
        ->update(['_in_verified'=>$verificador]);


        $newres = new Restock;
        $newres->_staff = $verificador;
        $newres->_requisition = $pedido;
        $newres->_status = $status;
        $newres->save();
        $newres->fresh()->toArray();
        return response()->json($newres,200);
    }

    public function getSalida(Request $request){
        $salida = $request->all();
        $stores = Stores::find(1);
        // $ip = $stores->ip_address;
        $ip = '192.168.10.112:1619';
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
        $supply = $request->suply;

        $change = DB::connection('vizapi')
        ->table('requisition_partitions')
        ->where([['_requisition',$pedido],['_suplier_id',$supply]])
        ->update(['_status'=>$status]);

        $partition = DB::connection('vizapi')
        ->table('requisition_partitions AS P')
        ->join('requisition_process AS RP','P._status','RP.id')
        ->select('P.*','RP.name')
        ->where([['_requisition',$pedido],['_suplier_id',$supply]])
        ->first();

        $idlog = DB::connection('vizapi')->table('partition_logs')->max('id') + 1;

        $inslo = [
            'id'=>$idlog,
            '_requisition'=>$pedido,
            '_partition'=>$partition->id,
            '_status'=>$status,
            'details'=>json_encode(['responsable'=>'vizapp']),
        ];

        $logs = DB::connection('vizapi')
        ->table('partition_logs')
        ->insert($inslo);
        // if($change > 0){
            $endpart = $this->verifyPartition($pedido);
            $res = [
                "partition"=>$partition,
                "partitionsEnd"=>$endpart
            ];
            return response()->json($res,200);
        // }else{
            // return response()->json('No se hizo el cambio de status',500);
        // }
    }

    public function sendMessages(Request $request){
        $url = env('URLWHA');
        $token = env('WATO');
        $pedido = $request->id;
        $suply = $request->suply;
        $sucursal = $request->store;

        $response = Http::withOptions([
            'verify' => false, // Esto deshabilita la verificación SSL, similar a CURLOPT_SSL_VERIFYHOST y CURLOPT_SSL_VERIFYPEER en cURL
        ])->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'token' => $token,
            'to' => '+525573461022',
            'body' => 'El colaborador '.$suply.' entrego la salida  '.$pedido.' a la sucursal '.$sucursal,
        ]);
    }

    public function verifyPartition($pedido){
        $partition = DB::connection('vizapi')
        ->table('requisition_partitions')
        ->where([['_requisition',$pedido]])
        ->min('_status');
        return $partition;
    }
}
