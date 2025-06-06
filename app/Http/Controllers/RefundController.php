<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\Refund;
use App\Models\RefundType;
use App\Models\RefundBodie;
use App\Models\RefundState;
use App\Models\RefundProvider;
use Illuminate\Support\Facades\Http;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class RefundController extends Controller
{
    public function Index($sid){
        $refunds = [
            'to'=>[],
            'from'=>[],
        ];
        $refunds['to']= Refund::with(['storefrom','storeto','status','type','createdby','bodie'])
                ->where('_store_to',$sid)
                ->get();
        $refunds['from']= Refund::with(['storefrom','storeto','status','type','createdby','bodie'])
                ->where('_store_from',$sid)
                ->get();

        return response()->json([
            'refunds' => $refunds,
            'types' => RefundType::all(),
            'provider' => RefundProvider::all(),
            'status'=> RefundState::all()
        ]);
    }

    public function getRefund($sid,$rid){
        $refund = Refund::with(['storefrom','storeto','status','type','createdby','bodie'])->where([['id',$rid],['_store_from',$sid]])->first();
        return response()->json($refund,200);
    }

    public function getRefundTo($sid,$rid){
        $refund = Refund::with(['storefrom','storeto','status','type','createdby','bodie'])->where([['id',$rid],['_store_to',$sid]])->first();
        return response()->json($refund,200);
    }

    public function addRefund(Request $request){
        $refund = $request->all();
        $nwRefund = new Refund;
        $nwRefund->reference = $refund['reference'];
        $nwRefund->_provider = $refund['provider']['id'];
        $nwRefund->_warehouse = $refund['_warehouse'];
        $nwRefund->_type = $refund['type']['id'];
        $nwRefund->_status = $refund['_status'];
        $nwRefund->_store_from = $refund['_store_from'];
        $nwRefund->_store_to = $refund['store_to'];
        $nwRefund->_created_by = $refund['_created_by'];
        $nwRefund->save();
        $res = $nwRefund->load(['storefrom','storeto','status','type']);
        if($res){
            return response()->json($res,200);
        }else{
            return response()->json('No se realizo la devolucion',500);
        }
    }

    public function addProduct(Request $request){
        $product = $request->all();
        $nwProduct = new RefundBodie;
        $nwProduct->_refund = $product['_refund'];
        $nwProduct->product = $product['product'];
        $nwProduct->description = $product['description'];
        $nwProduct->to_delivered = $product['to_delivered'];
        $nwProduct->to_received = 0;
        $nwProduct->price = $product['price'];
        $nwProduct->save();
        if($nwProduct){
            return response()->json($nwProduct,200);
        }else{
            return response()->json('No se logro insertar el producto',500);
        }
    }

    public function editProduct(Request $request){
        $product = $request->all();
        $update = RefundBodie::where([['_refund',$product['_refund']],['product',$product['product']]])->update(['to_delivered'=>$product['to_delivered']]);
        if($update){
            return response()->json('Producto Editado',200);
        }
    }

    public function deleteProduct(Request $request){
        $product = $request->all();
        $delete = RefundBodie::where([['_refund',$product['_refund']],['product',$product['product']]])->delete();
        if($delete){
            return response()->json('Producto Eliminado',200);
        }else{
            return response()->json('Hubo un problema al eliminar el producto',500);
        }
    }

    public function endRefund(Request $request){
        $id = $request->id;
        $refund = Refund::with(['storefrom','storeto','status','type','createdby','bodie'])->where([['id',$id]])->first();
        $insRefund = http::post($refund->storefrom['ip_address'].'/storetools/public/api/refunds/addRefund',$refund);
        // $insRefund = http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/addRefund',$refund);
        if($insRefund->status() == 200){
            $update = Refund::find($id);
            $update->_status = 2;
            $update->fs_id = $insRefund->body();
            $update->save();
            $res = $update->load(['storefrom','storeto','status','type','createdby','bodie']);
            return response()->json($res,200);
        }else{
            return response('No se logro generar la devolucion en la sucursal',500);
        }
    }

    public function nexState(Request $request){
        $id = $request->id;
        $uid = $request->uid;
        $update = Refund::find($id);
        $update->_status = 3;
        $update->_receipt_by = $uid;
        $update->save();
        if($update){
            return response()->json('Se cambio el status');
        }else{
            return response()->json('no se logro cambiar el status');
        }
    }


    public function editProductReceipt(Request $request){
        $product = $request->all();
        $update = RefundBodie::where([['_refund',$product['_refund']],['product',$product['product']]])->update(['to_received'=>$product['to_received']]);
        if($update){
            return response()->json('Producto Editado',200);
        }
    }

    public function finallyRefund(Request $request){
        $id = $request->id;
        $refund = Refund::with(['storefrom','storeto','status','type','createdby','bodie'])->where([['id',$id]])->first();
        $cedis = Stores::find(1);
        if($refund->_type == 1){// se genera el abono con articulos en cedis,
            $addSeason = http::post($cedis->ip_address.'/storetools/public/api/refunds/genAbono',$refund);
            // $addSeason = http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/genAbono',$refund);
            if($addSeason->status() == 200){
                $update = Refund::find($id);
                $update->_status = 4;
                $update->season_ticket = $addSeason->body();
                $update->save();
                $res = $update->load(['storefrom','storeto','status','type','createdby','bodie']);
                return response()->json($res,200);
            }else{
                return response('No se logro generar el abono en cedis',500);
            }
        }else if($refund->_type == 2){// se genera abono factura y entrada en sucursal receptora
            $addSeason = http::post($cedis->ip_address.'/storetools/public/api/refunds/genAbonoTras',$refund);
            // $addSeason = http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/genAbonoTras',$refund);
            if($addSeason->status() == 200){
                $update = Refund::find($id);
                $update->invoice = $addSeason['salida'];
                $update->season_ticket = $addSeason['abono'];
                $update->save();
                $res = $update->load(['storefrom','storeto','status','type','createdby','bodie']);
                if($res){
                    $addEntry = http::post($refund->storeto['ip_address'].'/storetools/public/api/refunds/genEntry',$refund);
                    // $addEntry = http::post('192.168.10.160:1619'.'/storetools/public/api/refunds/genEntry',$refund);
                    if($addEntry->status() == 200){
                        $updateentr = Refund::find($id);
                        $update->_status = 4;
                        $update->entry = $addEntry->body();
                        $update->save();
                        $res = $update->load(['storefrom','storeto','status','type','createdby','bodie']);
                        return response()->json($res,200);
                    }else{
                        return response()->json('No se logro realizar la entrada',500);
                    }
                }else{
                    return response()->json('No se logro actualizar la devolucion',500);
                }
            }else{
                return response('No se logro generar el abono y la salida en cedis',500);
            }

        }
    }

    public function getRefundDirerences($sid){
        $devs = Refund::with([
            'storefrom',
            'storeto',
            'status',
            'type',
            'createdby',
            'bodie' => function($q){ $q->where('to_delivered','!=','to_received');}
            ])
            ->where([['_store_to',$sid],['_status',4]])
            ->whereHas('bodie', function($q){   $q->whereColumn('to_delivered', '!=', 'to_received');})
            ->get();
        return response()->json($devs);

    }
}
