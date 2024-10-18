<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\Transfers;
use App\Models\Warehouses;
use Illuminate\Support\Facades\Http;
use App\Models\TransferBodies;
use Illuminate\Support\Facades\DB;


class TransferController extends Controller
{
    public function Index($sid){
        $now = now()->format('Y-m-d');
        $transfer = Transfers::with(['store','origin','destiny','bodie'])->where('_store',$sid)->whereDate('created_at',$now)->get();
        $warehouse = Warehouses::all();

        $resp = [
            'warehouses'=>$warehouse,
            'transfer'=>$transfer,
        ];

        return response()->json($resp,200);
    }

    public function getTransfersDate(Request $request){
        $fechas = $request->date;
        $sid = $request->store;
        if(isset($fechas['from'])){
            $desde = $fechas['from'];
            $hasta = $fechas['to'];
        }else{
            $desde = $fechas;
            $hasta = $fechas;
        }
        $transfer = Transfers::with(['store','origin','destiny','bodie'])->where('_store',$sid)->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])->get();
        return response()->json($transfer,200);

    }

    public function addTransfer(Request $request){
        $transfer = $request->all();
        $store = Stores::find($transfer['_store']);
        $ip = $store->ip_address;
        // $ip = '192.168.10.160:1619';
        $insTraAcc = http::post($ip.'/storetools/public/api/TransferBW/addTransfer',$transfer);
        $status = $insTraAcc->status();
        if($status == 201){
            $res = json_decode($insTraAcc);
            if($res->state){
                $nwtransfer = new Transfers;
                $nwtransfer->_store = $transfer['_store'];
                $nwtransfer->created_by = $transfer['created_by'];
                $nwtransfer->_origin = $transfer['_origin']['id'];
                $nwtransfer->_destiny = $transfer['_destiny']['id'];
                $nwtransfer->notes = $transfer['notes'];
                $nwtransfer->code_fs = $res->traspaso;
                $nwtransfer->save();
                $nwtransfer->fresh()->toArray();
                return response()->json($nwtransfer,200);
            }else{
                return response()->json('Hubo un problema en la creacion de el traspaso ',500);
            }
        }else{
            return response()->json('Hubo un problema en la creacion de el traspaso ',500);
        }
    }

    public function getTransfer($oid){
        $transfer = Transfers::with(['store','origin','destiny','bodie'])->find($oid);
        return response()->json($transfer);
    }

    public function addProduct(Request $request){
        $product = $request->all();
        $add = new TransferBodies;
        $add->_transfer = $product['_transfer'];
        $add->product = $product['product'];
        $add->description = $product['description'];
        $add->amount = $product['amount'];
        $add->save();
        if($add){
            return response()->json('Producto Insertado',200);
        }else{
            return response()->json('Hubo problema al agregar el producto',500);
        }
    }

    public function addProductMasive(Request $request){
        $products = $request->all();
        $add = TransferBodies::insert($products);
        if($add){
            return response()->json('Producto Insertado',200);
        }else{
            return response()->json('Hubo problema al agregar el producto',500);
        }

    }

    public function editProduct(Request $request){
        $product = $request->all();
        $modify = TransferBodies::where([['_transfer',$product['_transfer']],['product',$product['product']]])->update(['amount'=>$product['amount']]);
        if($modify){
            return response()->json('Producto Editado',200);
        }
    }

    public function removeProduct(Request $request){
        $product = $request->all();
        $delete = TransferBodies::where([['_transfer',$product['_transfer']],['product',$product['product']]])->delete();
        if($delete){
            return response()->json('Producto Eliminado',200);
        }else{
            return response()->json('Hubo un problema al eliminar el producto',500);
        }
    }

    public function endTransfer(Request $request){
        $transfer = $request->traspaso;
        $products = $request->products;
        $user = $request->user;
        $data = [
            "traspaso"=>$transfer,
            "products"=>$products
        ];

        $store = Stores::find($transfer['_store']);
        $ip = $store->ip_address;
        // $ip = '192.168.10.160:1619';
        $insTraAcc = http::post($ip.'/storetools/public/api/TransferBW/endTransfer',$data);
        $status = $insTraAcc->status();
        if($status == 201){
            $traspaso = Transfers::where('id',$transfer['id'])->update(['updated_by'=>$user]);
            if($traspaso){
                return response()->json('Traspaso Finalizado',200);
            }

        }else{
            return response()->json(json_decode($insTraAcc),200);
        }
    }

    public function transferPreventa(Request $request){
        $notfound = [];
        $ok = [];
        $pedidos = $request->codes;
        $sucursal = $request->_workpoint;
        foreach($pedidos as $pedido){
            $change = DB::connection('vizapi')->table('orders')->where([['id',$pedido],['_workpoint_from',$sucursal]])->first();
            if($change){
                array_push($ok,$pedido);
            }else{
                array_push($notfound,$pedido);
            }
        }
        if(count($ok) > 0){

            $products = DB::connection('vizapi')->table('product_ordered as PO')->join('products AS P','P.id','PO._product')->whereIn('PO._order',$ok)->select('P.code AS product', 'P.description AS description', 'PO.units AS amount')->get();
            $res = [
                "products"=>$products,
                "Encontrados"=>$ok,
                "Faltantes"=>$notfound
            ];
           return response()->json($res,200);
        }else{
            return response()->json('No hay pedidos que buscar',200);
        }
    }
}
