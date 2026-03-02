<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Stores;
use App\Models\Refund;
use App\Models\RefundType;
use App\Models\RefundBodie;
use App\Models\RefundState;
use App\Models\RefundProvider;
use App\Models\ProductVA;
use App\Models\RefundLog;
use Illuminate\Support\Facades\Http;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class RefundController extends Controller
{
    public function Index(Request $request){//yasta
        $sid = $request->sid();
        $refunds = [
            'to'=>[],
            'from'=>[],
        ];
        $refunds['to']= Refund::with(['origin.store','destiny.store','status','type','createdby','receiptby','bodie'])//de
                ->whereHas('origin', function($q) use($sid) {
                    $q->where('_store',$sid);
                })
                ->get();
        $refunds['from']= Refund::with(['origin.store','destiny.store','status','type','createdby','receiptby' ,'bodie'])//para
                ->whereHas('destiny', function($q) use($sid) {
                    $q->where('_store',$sid);
                })
                ->get();

        return response()->json([
            'refunds' => $refunds,
            'types' => RefundType::all(),
            // 'provider' => RefundProvider::all(),
            'status'=> RefundState::all()
        ]);
    }

    public function getRefund($rid){//yasta
        $refund = Refund::with(['origin.store','destiny.store','status','type','createdby','receiptby','bodie'])->find($rid);
        if($refund){
            return response()->json($refund,200);
        }else{
            return response()->json('No se encuentra',500);
        }
    }

    public function getRefundTo($rid){//yasta
        $refund = Refund::with(['origin.store','destiny.store','status','type','createdby','receiptby','bodie'])->find($rid);
        if($refund){
            return response()->json($refund,200);
        }else{
            return response()->json('No se encuentra',500);
        }
    }

    public function addRefund(Request $request){//yasta
        $refund = $request->all();
        $refund['_created_by'] = $request->uid();
        $transfer = Refund::create($refund);
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

    public function addProduct(Request $request){
        DB::beginTransaction();
        try{
            $transfer = Refund::findOrFail($request->_transfer);
            $transfer->bodie()->attach(
                $request->_product,
               ['to_delivered' => $request->to_delivered]
               );
            DB::commit();
            return response()->json('Producto Insertado', 200);
        }catch( \Exception $e){
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function editProduct(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Refund::findOrFail($request->_transfer);
            $transfer->bodie()->updateExistingPivot(
                $request->_product,
                ['to_delivered' => $request->to_delivered]
            );
            DB::commit();
            return response()->json('Producto Actualizado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function deleteProduct(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Refund::findOrFail($request->_transfer);
            $transfer->bodie()->detach($request->_product);
            DB::commit();
            return response()->json('Producto Eliminado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function endRefund(Request $request){
     DB::beginTransaction();
        try {
            $transfer = Refund::findOrFail($request->id);
            if ($transfer->_state != 1) {
                throw new \Exception("Transferencia ya procesada");
            }
            $transfer->_state = 2;
            $transfer->save();
            $details= ["tipo"=>'Termino Origen',"transfer"=>$request->all()];
            $log = $this->createLog($transfer->id,2,$request->uid(),$details);
            //actualizacion de stock
            $origin = $request->origin['id'];
            $destiny = $request->destiny['id'];
            $products = $request->bodie;
            foreach($products as $product){
                $productId = $product['id'];
                $amount    = $product['pivot']['to_delivered'];
                $product = ProductVA::findOrFail($productId);
                $product->stocks()
                    ->where('_warehouse', $origin)
                    ->decrement('_current', $amount);
                $product->stocks()
                    ->where('_warehouse', $origin)
                    ->decrement('available', $amount);
                $product->stocks()
                    ->where('_warehouse', $destiny)
                    ->increment('in_coming', $amount);
            }
            $transfer->load(['origin.store','destiny.store','status','type','createdby','bodie']);
            DB::commit();
            return response()->json($transfer, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }

    }

    public function nexState(Request $request){//para recepcion
        DB::beginTransaction();
            try {
                $transfer = Refund::findOrFail($request->id);

                if ($transfer->_state != 2) {
                    throw new \Exception("Transferencia ya Recibida");
                }
                $transfer->_state = 3;
                $transfer->_receipt_by = $request->uid();
                $transfer->date_receipt = now();
                $transfer->save();
                $details= ["tipo"=>'Recibido'];
                $log = $this->createLog($transfer->id,3,$request->uid(),$details);
                $transfer->load(['origin.store','destiny.store','status','type','createdby','bodie']);
                DB::commit();
                return response()->json($transfer, 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json($e->getMessage(), 500);
        }
    }
    public function nexStateValid(Request $request){//para recepcion comenzar a validar
        DB::beginTransaction();
            try {
                $transfer = Refund::findOrFail($request->id);
                if ($transfer->_state != 3) {
                    throw new \Exception("Transferencia ya Recibida");
                }
                $transfer->_state = 4;
                $transfer->_receipt_by = $request->uid();
                $transfer->date_receipt = now();
                $transfer->save();
                $details= ["tipo"=>'Comienza A validar'];
                $log = $this->createLog($transfer->id,4,$request->uid(),$details);
                $transfer->load(['origin.store','destiny.store','status','type','createdby','bodie']);
                DB::commit();
                return response()->json($transfer, 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json($e->getMessage(), 500);
        }
    }

    public function editProductReceipt(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Refund::findOrFail($request->_transfer);
            $transfer->bodie()->updateExistingPivot(
                $request->_product,
                ['to_received' => $request->to_received]
            );
            DB::commit();
            return response()->json('Producto Actualizado', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function finallyRefund(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Refund::findOrFail($request->id);
            if ($transfer->_state != 4) {
                throw new \Exception("Transferencia ya procesada");
            }
            $transfer->_state = 5;
            $transfer->save();
            $details= ["tipo"=>'Termino Origen',"transfer"=>$request->all()];
            $log = $this->createLog($transfer->id,5,$request->uid(),$details);
            // $origin = $request->origin['id'];
            $destiny = $request->destiny['id'];
            $products = $request->bodie;
            foreach($products as $product){
                $productId = $product['id'];
                $amount = $product['pivot']['to_received'];
                $amountDelivered = $product['pivot']['to_delivered'];

                $product = ProductVA::findOrFail($productId);
                $product->stocks()
                    ->where('_warehouse', $destiny)
                    ->increment('_current', $amount);
                $product->stocks()
                    ->where('_warehouse', $destiny)
                    ->increment('available', $amount);
                $product->stocks()
                    ->where('_warehouse', $destiny)
                    ->decrement('in_coming', $amountDelivered);
            }
            $transfer->load(['origin.store','destiny.store','status','type','createdby','bodie']);
            DB::commit();
            return response()->json($transfer, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function nexStateUpdate(Request $request){//para recepcion comenzar a validar
        DB::beginTransaction();
            try {
                $transfer = Refund::findOrFail($request->id);
                if ($transfer->_state != 5) {
                    throw new \Exception("Transferencia ya Recibida");
                }
                $transfer->_state = 6;
                $transfer->save();
                $details= ["tipo"=>'Comienza a editar'];
                $log = $this->createLog($transfer->id,6,$request->uid(),$details);
                $transfer->load(['origin.store','destiny.store','status','type','createdby','bodie']);
                DB::commit();
                return response()->json($transfer, 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json($e->getMessage(), 500);
        }
    }

    public function finishUpdate(Request $request){
        DB::beginTransaction();
        try {
            $transfer = Refund::findOrFail($request->transfer['id']);
            if ($transfer->_state != 6) {
                throw new \Exception("Transferencia ya procesada");
            }
            $transfer->_state = 5;
            $transfer->save();
            $details= ["tipo"=>'Termino Actualizacion',"transfer"=>$request->all()];
            $log = $this->createLog($transfer->id,5,$request->uid(),$details);
            $origin = $request->transfer['origin']['id'];
            $destiny = $request->transfer['destiny']['id'];
            $products = $request->cambios;
            foreach($products as $product){
                $productId = $product['id'];
                $diffDelivered = $product['to_delivered']['diferencia'];
                $diffReceived  = $product['to_received']['diferencia'];
                $newDelivered = $product['to_delivered']['ahora'];
                $newReceived  = $product['to_received']['ahora'];
                $transfer->bodie()->updateExistingPivot(
                $productId,
                    [
                        'to_delivered' => $newDelivered,
                        'to_received'  => $newReceived
                    ]
                );
                $productModel = ProductVA::findOrFail($productId);
                if($diffDelivered != 0){
                    $productModel->stocks()
                        ->where('_warehouse', $origin)
                        ->decrement('_current', $diffDelivered);

                    $productModel->stocks()
                        ->where('_warehouse', $origin)
                        ->decrement('available', $diffDelivered);
                }
                if($diffReceived != 0){
                    $productModel->stocks()
                        ->where('_warehouse', $destiny)
                        ->increment('_current', $diffReceived);

                    $productModel->stocks()
                        ->where('_warehouse', $destiny)
                        ->increment('available', $diffReceived);
                }
            }
            $transfer->load(['origin.store','destiny.store','status','type','createdby','bodie']);
            DB::commit();
            return response()->json($transfer, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }


    public function getRefundDirerences($sid){
        $devs = Refund::with([
            'storefrom',
            'storeto',
            'status',
            'type',
            'createdby',
            'receiptby',
            'bodie' => function($q){ $q->where('to_delivered','!=','to_received');}
            ])
            ->where([['_store_to',$sid],['_status',4]])
            ->whereHas('bodie', function($q){   $q->whereColumn('to_delivered', '!=', 'to_received');})
            ->get();
        return response()->json($devs);

    }

    public function correction(Request $request){
        $refun = $request->all();
        $response = [
            "Eliminar"=>[],
            "Salida"=>[],
            "Entrada"=>[],
            "abonoysalida"=>[]
        ];
        $originalIndexed = [];
        $deletedProducts = [];
        $changedInDestination = [];
        $changedInOrigin = [];
        $cedis = Stores::find(1);
        $refOri = Refund::with(['bodie'])->where('id',$refun['id'])->first();
        if($refOri){
            $productsOri = $refOri->bodie->toArray(); // Productos originales
            $productsDes = $refun['bodie'];           // Productos modificados

            foreach ($productsOri as $product) {
                $originalIndexed[$product['product']] = [
                    'product' => $product['product'],
                    'to_received' => isset($product['to_received']) ? (int)$product['to_received'] : 0,
                    'to_delivered' => isset($product['to_delivered']) ? (int)$product['to_delivered'] : 0,
                ];
            }

            foreach ($productsDes as $product) {
                $id = $product['product'];
                $refund = $product['_refund'];
                $dev = $refOri->fs_id;
                $abo = $refOri->season_ticket;
                $fac = $refOri->invoice;
                $ent = $refOri->entry;
                $currentToReceived = isset($product['to_received']) ? (int)$product['to_received'] : 0;
                $currentToDelivered = isset($product['to_delivered']) ? (int)$product['to_delivered'] : 0;

                if (isset($originalIndexed[$id])) {
                    $original = $originalIndexed[$id];
                    if ($original['to_delivered'] !== $currentToDelivered) {
                        $changedInOrigin[] = [
                            'idRefund'=>$refund,
                            'refund'=>$dev,
                            'abono'=>$abo,
                            'factura'=>$fac,
                            'ent'=>$ent,
                            'product' => $id,
                            'from' => $original['to_delivered'],
                            'to' => $currentToDelivered,
                        ];
                    }
                    if ($original['to_received'] !== $currentToReceived) {
                        $changedInDestination[] = [
                            'idRefund'=>$refund,
                            'refund'=>$dev,
                            'abono'=>$abo,
                            'factura'=>$fac,
                            'ent'=>$ent,
                            'product' => $id,
                            'from' => $original['to_received'],
                            'to' => $currentToReceived,
                        ];
                    }
                    unset($originalIndexed[$id]);
                }
            }

            if(count($changedInOrigin) > 0){//cambiar total solo en devolucion
                foreach($changedInOrigin as $delivered){
                    $refBodie = RefundBodie::where([['_refund',$delivered['idRefund']],['product',$delivered['product']]])->update(['to_delivered'=>$delivered['to']]);
                }
                $ModDelivered = Http::post($refOri->storefrom['ip_address'].'/storetools/public/api/refunds/editRefund',$changedInOrigin);
                // $ModDelivered = Http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/editRefund',$changedInOrigin);
                if($ModDelivered->status() == 201){
                    $response['Salida'] = $ModDelivered->json();
                }
            }

            if(count($changedInDestination) > 0){//cambiar todo
                foreach($changedInDestination as $received){
                    $refBodie = RefundBodie::where([['_refund',$received['idRefund']],['product',$received['product']]])->update(['to_received'=>$received['to']]);
                }
                $ModReceived = Http::post($refOri->storeto['ip_address'].'/storetools/public/api/refunds/editEntry',$changedInDestination);
                // $ModReceived = Http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/editEntry',$changedInDestination);
                if($ModReceived->status()==201){
                    $responseRece = $ModReceived->json();
                    $response['Entrada'] = $responseRece;
                    if($refOri->type['id'] == 2){
                        $dataAbono = [
                            "folioAbono"=>$abo,
                            "folioFactura"=>$fac,
                            "total"=>$responseRece['total'],
                        ];
                        $modSeason = Http::post($cedis->ip_address.'/storetools/public/api/refunds/editSeason',$changedInDestination);
                        // $modSeason = Http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/editSeason',$dataAbono);
                        if($modSeason->status() == 201 ){
                            $response['abonoysalida'] = $ModReceived;
                        }
                    }
                }
            }

            $result = [
                'toDelivered' => $changedInOrigin,//enviado
                'toReceived' => $changedInDestination,//recibido
                'res' => $response,
            ];

            return response()->json($result);
        }
    }

    public function createLog($transfer,$state,$user,$details){
        $createLog = RefundLog::create([
            "_transfer"=>$transfer,
            "_state"=>$state,
            "_user"=>$user,
            "details"=>json_encode($details)
        ]);
        return $createLog;
    }


}
