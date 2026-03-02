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
use App\Models\ProductStockVA;
use App\Models\AccountVA;
use App\Models\CellerLogVA;
use App\Models\Warehouses;
use App\Models\Transfers;
use App\Models\TransferBodies;
use App\Models\TransferWarehouseLog;
use App\Models\ProductCategoriesVA;
use Carbon\Carbon;


class WarehousesController extends Controller
{
    public function Index(Request $request){
        $sid = $request->sid();
        $res = [
            'warehouses'=>Warehouses::with('type','state')->where('_store',$sid)->get(),
            'stores'=>Stores::with(['warehouses' => fn($q) => $q->where([['_type',1],['_state',1]])])->where([['_state',1]])->get(),
        ];
        return response()->json($res,200);
    }


    public function setMin(Request $request){
        $updated = ProductStockVA::where('_product', $request->_product)
            ->where('_warehouse', $request->_warehouse)
            ->update(['_min' => $request->_min]);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Mínimo actualizado correctamente'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro para actualizar'
            ]);
        }
    }

    public function setMax(Request $request){
        $updated = ProductStockVA::where('_product', $request->_product)
            ->where('_warehouse', $request->_warehouse)
            ->update(['_max' => $request->_max]);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Maximo actualizado correctamente'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro para actualizar'
            ]);
        }
    }

    public function setMassisveMinMax(Request $request){
        $actualizados = [
            "goals"=>0,
            "fails"=>0
        ];
        $products = $request->products;
        $warehouse = $request->_warehouse;
        foreach($products as $product){
            $updated = ProductStockVA::where('_product', $product['id'])
                ->where('_warehouse', $warehouse)
                ->update(['_max' => $product['max'], '_min' => $product['min'] ]);
            if ($updated) {
                $actualizados['goals']++;
            } else {
                $actualizados['fails']++;
            }
        }
        return response()->json($actualizados,200);
    }

    public function updateStatusProduct(Request $request){
        $updated = ProductStockVA::where('_product', $request->_product)
            ->where('_warehouse', $request->_warehouse)
            ->update(['_state' => $request->_status]);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Maximo actualizado correctamente'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro para actualizar'
            ]);
        }
    }


}
