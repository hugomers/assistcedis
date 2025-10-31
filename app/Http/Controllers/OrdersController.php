<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SeasonsVA;
use App\Models\AccountVA;
use App\Models\OrderVA;
use App\Models\OrderProcessVA;
use App\Models\ClientVA;
use App\Models\OrderLogVA;
use App\Models\ProductOrderedVA;


class OrdersController extends Controller
{
    // public function getOrder($ord){
    //     $order = DB::connect('vizapi')->table('orders')->where('id',$ord)->get();
    //     return response($order);
    // }

    public function getRules(){
        $rules = SeasonsVA::with('rules')->get();
        return response()->json($rules);
    }

    public function getOrder(Request $request){
        $uid = $request->uid;
        $ord = $request->route('ord');
        $ip = $request->ip();
        $user = AccountVA::find($uid);
        if (!$user) {
            return response()->json('Usuario no encontrado', 404);
        }
        $order = OrderVA::find($ord);
        if (!$order) {
            return response()->json('Pedido no encontrado', 404);
        }
        if ($order->_status == 5) {
            $order->_status = 7;
            $order->save();
            $res = $order->fresh();
            $this->createLog($res->id, 7, ['user' => $user, 'ip' => $ip], 'App\User', $user->id);
            return response()->json($res);
        } else {
            return response()->json('El pedido ya está verificado', 401);
        }
    }

    public function getOrderVerify($ord){
        $rules = SeasonsVA::with('rules')->get();
        $order = OrderVA::find($ord);
        if($order->_status == 7){
            $order->load(['products.category.familia.seccion','client','created_by','products.prices']);

            $res = [
                "order"=>$order,
                "rules"=>$rules,
            ];
            return response()->json($res);
        }else{
            return response()->json('El pedido no esta en estado de verificacion', 401);
        }
    }

    public function getOrderAdd(Request $request){
        $oid = $request->oid;
        $uid = $request->uid;
        $ord = $request->route('ord');
        $ip = $request->ip();
        $user = AccountVA::find($uid);
        if (!$user) {
            return response()->json('Usuario no encontrado', 404);
        }
        $order = OrderVA::find($ord);
        if($order){
            if($order->_status == 5){
                $order->_status = 100;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 100, ['user' => $user, 'ip' => $ip, 'message'=>'Cancelado por verificacion actual',"order"=>$oid], 'App\User', $user->id);

                return response()->json($res);
            }else{
                return response()->json('El pedido no esta preparado para la verificacion', 401);
            }
        }else{
            return response()->json('No se encuentra ningun pedido con ese id',404);
        }

    }

    public function editProduct(Request $request){
        $product = $request->all();
        $upd = [
            "toDelivered"=>$product['toDelivered'],
            "amountDelivered"=>$product['amountDelivered'],
            "_price_list"=>$product['_price_list'],
            "price"=>$product['price'],
            "total"=>$product['total'],
        ];
        $update = ProductOrderedVA::where([['_order',$product['_order']],['_product',$product['_product']]])->update($upd);
        return response()->json($update,200);
    }
    public function addProduct(Request $request){
        $product = $request->all();
        $nwProduct = new ProductOrderedVA;
        $nwProduct->kit = '';
        $nwProduct->units = $product['units'];
        $nwProduct->price = $product['price'];
        $nwProduct->_product = $product['_product'];
        $nwProduct->_order = $product['_order'];
        $nwProduct->_supply_by = $product['_supply_by'];
        $nwProduct->_price_list = $product['_price_list'];
        $nwProduct->comments = 'Agregado en Verificacion';
        $nwProduct->total = $product['total'];
        $nwProduct->toDelivered = $product['toDelivered'];
        $nwProduct->amountDelivered = $product['amountDelivered'];
        $res = $nwProduct->save();
        return response()->json($res,200);
    }

    public function deleteProduct(Request $request){
        $product = $request->all();
        $delete = ProductOrderedVA::where([['_order',$product['_order']],['_product',$product['_product']]])->delete();
        return response()->json($delete,200);
    }
    public function updateProductPrices(Request $request){
        $products = $request->all();
        foreach ($products as $product) {
            $upd = [
                "_price_list"=>$product['_price_list'],
                "price"=>$product['price'],
                "total"=>$product['total'],
            ];
            $update = ProductOrderedVA::where([['_order',$product['_order']],['_product',$product['_product']]])->update($upd);
        }

        return response()->json($products);
    }

    public function getClient(Request $request){
        $val = $request->val;
        $buscar = ClientVA::where(function($query) use ($val) {
        $query->where('id', 'like', '%' . $val . '%')
              ->orWhere('name', 'like', '%' . $val . '%')
              ->orWhere('phone', 'like', '%' . $val . '%')
              ->orWhere('store_name', 'like', '%' . $val . '%');
        })
        ->where(function($query) {
            $query->where('id', 0)
                ->orWhere('id', '>=', 36);
        })
        ->get();
        if($buscar->isEmpty()){
            $buscar = ClientVA::where('id', 0)->get();
        }

        return response()->json($buscar,200);
    }

    public function changeClientOrder(Request $request){
        $order = $request->order;
        $client = $request->client;

        $updOrder = OrderVA::find($order);
        $updOrder->_client = $client;
        $res = $updOrder->save();
        return response()->json($res);
    }
    public function nextState(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status == 7){
                $order->_status = 8;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 8, ['user' => $user, 'ip' => $ip,"order"=>$oid], 'App\User', $user->id);
                return response()->json($res);
            }else{
                return response()->json('El pedido aun no esta validado',401);
            }
        }else{
            return response()->json('No hay ninguna Order con ese numero',404);
        }
    }

    public function nextStateFinish(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status == 9){
                $order->_status = 10;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 10, ['user' => $user, 'ip' => $ip,"order"=>$oid], 'App\User', $user->id);
                return response()->json($res);
            }else{
                return response()->json('El pedido aun no esta validado',401);
            }
        }else{
            return response()->json('No hay ninguna Order con ese numero',404);
        }
    }

    public function getOrderCash(Request $request){
        $oid = $request->oid;//order
        $uid = $request->uid;//usuario
        $user = AccountVA::find($uid);
        $ip = $request->ip();//ip
        $order = OrderVA::find($oid);
        if($order){
            if($order->_status == 8){
                $order->_status = 9;
                $order->save();
                $res = $order->load(['products.category.familia.seccion','client','created_by','products.prices']);
                $this->createLog($order->id, 9, ['user' => $user, 'ip' => $ip], 'App\User', $user->id);
                return response()->json($res);
            }else{
                return response()->json('El pedido no se puede cobrar',401);
            }
        }else{
            return response()->json('No hay ninguna Order con ese numero',404);
        }
    }

    public function createLog($_order, $_status, $details, $_type, $user){
        $log = new OrderLogVA;
        $log->_order = $_order;
        $log->_status = $_status;
        $log->details = json_encode($details);
        $log->_type = $_type;
        $log->_responsable = $user;
        $log->save();
        return $log;
    }


}
