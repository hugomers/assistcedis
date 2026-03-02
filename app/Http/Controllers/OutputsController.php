<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\OuputInternal;
use App\Models\Warehouses;
use Illuminate\Support\Facades\Http;
use App\Models\OuputBodie;
use App\Models\OutputLog;
use App\Models\OutputState;
use Illuminate\Support\Facades\DB;
use App\Models\ProductVA;



class OutputsController extends Controller
{
    public function Index(Request $request){
        $output = OuputInternal::with(['warehouse','bodie','createdby','modifyby','state'])->get();
        $states = OutputState::all();
        $res = [
            "outs"=>$output,
            "states"=>$states,
        ];
        return response()->json($res,200);
    }

    public function addOuts(Request $request){//yasta
        $output = $request->all();
        $output['_created_by'] = $request->uid();
        $transfer = OuputInternal::create($output);
        if($transfer){
            $details = [
                "notas"=>$transfer->notes
            ];
            $log = $this->createLog($transfer->id,1,$request->uid(),$details);
        return response()->json($transfer);
        }else{
            return response()->json('No se realizo la devolucion',500);
        }
    }

    public function getOutput($oid){//yasata
        $transfer = OuputInternal::with(['warehouse','bodie','createdby','modifyby','state'])->find($oid);
        return response()->json($transfer);
    }

    public function addProduct(Request $request){//yasta
        DB::beginTransaction();
        try{
            $transfer = OuputInternal::findOrFail($request->_transfer);
            $transfer->bodie()->attach(
                $request->_product,
               ['amount' => $request->amount]
               );
            DB::commit();
            return response()->json('Producto Insertado', 200);
        }catch( \Exception $e){
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function editProduct(Request $request){//yasta
        DB::beginTransaction();
        try {
            $transfer = OuputInternal::findOrFail($request->_transfer);
            $transfer->bodie()->updateExistingPivot(
                $request->_product,
                ['amount' => $request->amount]
            );
            DB::commit();
            return response()->json('Producto Actualizado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function removeProduct(Request $request){//yasta
        DB::beginTransaction();
        try {
            $transfer = OuputInternal::findOrFail($request->_transfer);
            $transfer->bodie()->detach($request->_product);
            DB::commit();
            return response()->json('Producto Eliminado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function endOutput(Request $request){//yasta
     DB::beginTransaction();
        try {
            $transfer = OuputInternal::findOrFail($request->id);
            if ($transfer->_state != 1) {
                throw new \Exception("Salida ya procesada");
            }
            $transfer->_state = 2;
            $transfer->save();
            $details= ["tipo"=>'Termino Salida',"salida"=>$request->all()];
            $log = $this->createLog($transfer->id,2,$request->uid(),$details);
            //actualizacion de stock
            $warehouse = $request->warehouse['id'];
            $products = $request->bodie;
            foreach($products as $product){
                $productId = $product['id'];
                $amount    = $product['pivot']['amount'];
                $product = ProductVA::findOrFail($productId);
                $product->stocks()
                    ->where('_warehouse', $warehouse)
                    ->decrement('_current', $amount);
                $product->stocks()
                    ->where('_warehouse', $warehouse)
                    ->decrement('available', $amount);
            }
            $transfer->load(['warehouse','bodie','createdby','modifyby','state']);
            DB::commit();
            return response()->json($transfer, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function outputPreventa(Request $request){//falta
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

    public function addProductMasive(Request $request){//falta
        $products = $request->all();
        $add = OuputBodie::insert($products);
        if($add){
            return response()->json('Producto Insertado',200);
        }else{
            return response()->json('Hubo problema al agregar el producto',500);
        }

    }

    public function createLog($transfer,$state,$user,$details){
        $createLog = OutputLog::create([
            "_output"=>$transfer,
            "_state"=>$state,
            "_user"=>$user,
            "details"=>json_encode($details)
        ]);
        return $createLog;
    }

}
