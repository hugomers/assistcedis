<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;
use App\Models\Opening;
use App\Models\OpeningType;
use App\Models\Stores;
use Illuminate\Support\Facades\Http;
use App\Models\ClientVA;
use App\Models\WorkpointVA;
use App\Models\CellerVA;
use App\Models\CellerSectionVA;
use App\Models\ProductVA;
use App\Models\AccountVA;
use App\Models\CellerLogVA;
use App\Models\Warehouses;
use App\Models\Transfers;
use App\Models\TransferBodies;
use App\Models\TransferWarehouseLog;
use App\Models\ProductCategoriesVA;
use Carbon\Carbon;


class TransferController extends Controller
{


    public function addingTransfer(Request $request){
        // return $request->all();
        $nwtra = $request->all();
        $nwtra['_created_by'] = $request->uid();
        $nwtra['_state'] = 1;

        $transfer = Transfers::create($nwtra);
        if($transfer){
            $details = [
                "notas"=>$transfer->notes
            ];
            $log = $this->createLog($transfer->id,1,$request->uid(),$details);
        return response()->json($transfer);

        }else{
            return response()->json('fALLO Algo :/',500);
        }
    }
    public function getTransfer($oid){
        $transfer = Transfers::with(['origin','destiny','bodie','created_by','state'])->find($oid);
        return response()->json($transfer);
    }
    public function getTransfers(Request $request){
        // $now = now()->format('Y-m-d');
        $fechas = $request->date;

        $sid = $request->sid();
        // return $fechas;
        if(isset($fechas['from'])){
            $desde = $fechas['from']." 00:00:00";
            $hasta = $fechas['to']." 23:59:59";
        }else{
            $desde = $fechas." 00:00:00";
            $hasta = $fechas." 23:59:59";
        }

        $transfer = Transfers::with(['origin','destiny','bodie','created_by','modify_by','state'])
        ->where(function($query) use ($sid) {
            $query->whereHas('origin', function($q) use ($sid) {
                $q->where('_store', $sid);
            })
            ->orWhereHas('destiny', function($q) use ($sid) {
                $q->where('_store', $sid);
            });
        })
        ->whereBetween('created_at',[$desde,$hasta])
        ->get();
        // $warehouse = Warehouses::all();

        $resp = [
            'transfer'=>$transfer,
        ];

        return response()->json($resp,200);
    }

    public function deleteTransfer(Request $request){
        $tid = $request->id;
        $transfer = Transfers::find($tid);
        $transfer->_state = 3;
        $transfer->save();
        $res = $transfer->load(['origin','destiny','bodie','created_by','modify_by','state']);
        if($res){
            $details= ["tipo"=>'Cancelacion'];
            $log = $this->createLog($transfer->id,3,$request->uid(),$details);
            return response()->json($transfer);
        }else{
            return response()->json('Hay un problema con el cambio de status',500);
        }
    }


    public function createLog($transfer,$state,$user,$details){
        $createLog = TransferWarehouseLog::create([
            "_transfer"=>$transfer,
            "_state"=>$state,
            "_user"=>$user,
            "details"=>json_encode($details)
        ]);
        return $createLog;
    }

    public function addProduct(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Transfers::findOrFail($request->_transfer);
            $transfer->bodie()->attach(
                $request->_product,
               ['amount' => $request->amount]
               );
            DB::commit();
            return response()->json('Producto Insertado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
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
        DB::beginTransaction();
        try {
            $transfer = Transfers::findOrFail($request->_transfer);
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

    public function removeProduct(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Transfers::findOrFail($request->_transfer);
            $transfer->bodie()->detach($request->_product);
            DB::commit();
            return response()->json('Producto Eliminado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function endTransfer(Request $request){
         DB::beginTransaction();
        try {
            $transfer = Transfers::findOrFail($request->id);
            if ($transfer->_state != 1) {
                throw new \Exception("Transferencia ya procesada");
            }
            $transfer->_state = 2;
            $transfer->save();
            $details= ["tipo"=>'Termino'];
            $log = $this->createLog($transfer->id,2,$request->uid(),$details);
            //actualizacion de stock

            $origin = $request->origin['id'];
            $destiny = $request->destiny['id'];
            $products = $request->bodie;
            foreach($products as $product){
                $productId = $product['id'];
                $amount    = $product['pivot']['amount'];
                $product = ProductVA::findOrFail($productId);
                $product->stocks()
                    ->where('_warehouse', $origin)
                    ->decrement('_current', $amount);
                $product->stocks()
                    ->where('_warehouse', $origin)
                    ->decrement('available', $amount);
                $product->stocks()
                    ->where('_warehouse', $destiny)
                    ->increment('_current', $amount);
                $product->stocks()
                    ->where('_warehouse', $destiny)
                    ->increment('available', $amount);
            }
            $transfer->load(['origin','destiny','bodie','created_by','modify_by','state']);
            DB::commit();
            return response()->json($transfer, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
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
