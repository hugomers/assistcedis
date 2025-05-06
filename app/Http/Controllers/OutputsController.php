<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\OuputInternal;
use App\Models\Warehouses;
use Illuminate\Support\Facades\Http;
use App\Models\OuputBodie;
use Illuminate\Support\Facades\DB;


class OutputsController extends Controller
{
    public function Index($sid){
        $now = now()->format('Y-m-d');
        $output = OuputInternal::with(['store','warehouse','bodie'])->where('_store',$sid)->whereDate('created_at',$now)->get();
        $warehouse = Warehouses::all();

        $resp = [
            'warehouses'=>$warehouse,
            'output'=>$output,
        ];

        return response()->json($resp,200);
    }

    public function getOutsDate(Request $request){
        $fechas = $request->date;
        $sid = $request->store;
        if(isset($fechas['from'])){
            $desde = $fechas['from'];
            $hasta = $fechas['to'];
        }else{
            $desde = $fechas;
            $hasta = $fechas;
        }
        $transfer = OuputInternal::with(['store','warehouse','bodie'])->where('_store',$sid)->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])->get();
        return response()->json($transfer,200);

    }



    public function addOuts(Request $request){
        $outs = $request->all();

        $store = Stores::find($outs['_store']);
        $ip = $store->ip_address;
        // $ip = '192.168.10.160:1619';
        $insOuts = http::post($ip.'/storetools/public/api/outsInternal/addOuts',$outs);
        $status = $insOuts->status();
        if($status == 201){
            $res = json_decode($insOuts);
            if($res->state){
                $nwtransfer = new OuputInternal;
                $nwtransfer->_store = $outs['_store'];
                $nwtransfer->created_by = $outs['created_by'];
                $nwtransfer->_warehouse = $outs['warehouse']['id'];
                $nwtransfer->notes = $outs['notes'];
                $nwtransfer->code_fs = $res->salida;
                $nwtransfer->save();
                $nwtransfer->fresh()->toArray();
                return response()->json($nwtransfer,200);
            }else{
                return response()->json('Hubo un problema en la creacion de el traspaso ',500);
            }
        }
        return $insOuts;
    }


    public function getOutput($oid){
        $transfer = OuputInternal::with(['store','warehouse','bodie'])->find($oid);
        return response()->json($transfer);
    }

    public function addProduct(Request $request){
        $product = $request->all();
        $add = new OuputBodie;
        $add->_output = $product['_transfer'];
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

    public function editProduct(Request $request){
        $product = $request->all();
        $modify = OuputBodie::where([['_output',$product['_output']],['product',$product['product']]])->update(['amount'=>$product['amount']]);
        if($modify){
            return response()->json('Producto Editado',200);
        }
    }

    public function removeProduct(Request $request){
        $product = $request->all();
        $delete = OuputBodie::where([['_output',$product['_output']],['product',$product['product']]])->delete();
        if($delete){
            return response()->json('Producto Eliminado',200);
        }else{
            return response()->json('Hubo un problema al eliminar el producto',500);
        }
    }

    public function endOutput(Request $request){
        $output = $request->output;
        $products = $request->products;
        $user = $request->user;
        $data = [
            "output"=>$output,
            "products"=>$products
        ];
        $store = Stores::find($output['_store']);
        $ip = $store->ip_address;
        // $ip = '192.168.10.160:1619';
        $insTraAcc = http::post($ip.'/storetools/public/api/outsInternal/endOuts',$data);
        $status = $insTraAcc->status();
        if($status == 201){
            $traspaso = OuputInternal::where('id',$output['id'])->update(['updated_by'=>$user]);
            if($traspaso){
                return response()->json('Salida Finalizada',200);
            }

        }else{
            return response()->json(json_decode($insTraAcc),200);
        }
    }

    public function outputPreventa(Request $request){
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

    public function addProductMasive(Request $request){
        $products = $request->all();
        $add = OuputBodie::insert($products);
        if($add){
            return response()->json('Producto Insertado',200);
        }else{
            return response()->json('Hubo problema al agregar el producto',500);
        }

    }

}
