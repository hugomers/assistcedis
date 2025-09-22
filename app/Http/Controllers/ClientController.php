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


class ClientController extends Controller
{
    public function Index(){
        $clients = ClientVA::with('credits.tickets.workpoint','credits.payments.counterpart')
        ->where('id','>',35)->get();
        $workpoints = WorkpointVA::where('active',1)->get();
        $res = [
            "clients"=>$clients,
            "workpoints"=>$workpoints,
        ];
        return response()->json($res,200);
    }

    public function getSalesC(Request $request){
        $workpoints = $request->workpoint;
        $date = $request->date;
        $getCredits = http::post('192.168.10.160:1619'.'/storetools/public/api/sales/getCredits',["date"=>$date]);
        // $getCredits = http::post($request->dominio.'/storetools/public/api/sales/openCash',$cashier);
        return $getCredits;

        return response()->json($request->all());
    }

    public function registerCredit(Request $request){
        $ticket = $request->all();

    }
}
