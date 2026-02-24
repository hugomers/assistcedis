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


class WarehousesController extends Controller
{
    public function Index(Request $request){
        $sid = $request->sid();
        $res = [
            'warehouses'=>Warehouses::with('type','state')->where('_store',$sid)->get(),
            'stores'=>Stores::with(['warehouses' => fn($q) => $q->where([['_type',1],['_state',1]])])->where([['_state',1],['_type',2]])->get(),
        ];
        return response()->json($res,200);
    }


}
