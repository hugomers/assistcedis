<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\Requisition;
use App\Models\RequisitionBodies;
use App\Models\RequisitionState;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class RequisitionController extends Controller
{
    public function getRequisitions(Request $request){
        $store = $request->route('id');
        // Detectar fecha actual
        $today = now(); // o Carbon::now() si prefieres
        $startOfPeriod = null;
        $endOfPeriod = null;
        // Definir el periodo
        if ($today->day <= 15) {
            // Primer periodo
            $startOfPeriod = $today->copy()->startOfMonth();
            $endOfPeriod = $today->copy()->startOfMonth()->addDays(14); // día 15
        } else {
            // Segundo periodo
            $startOfPeriod = $today->copy()->startOfMonth()->addDays(15); // día 16
            $endOfPeriod = $today->copy()->endOfMonth();
        }

        $requisition = Requisition::with(['user','status'])->where('_stores',$store)->withCount('bodie')->whereBetween('created_at', [$startOfPeriod, $endOfPeriod])->get();
        return response()->json($requisition);
    }

    public function getRequisitionsStore(Request $request){
        $today = now();
        $between = [
            "startDate"=>null,
            "finalDate"=>null
        ];
        if ($today->day <= 15) {
            $between['startDate']= $today->copy()->startOfMonth();
            $between['finalDate']= $today->copy()->startOfMonth()->addDays(14);

        } else {
            $between['startDate']= $today->copy()->startOfMonth()->addDays(15);
            $between['finalDate']= $today->copy()->endOfMonth();
        }
            $requisition = Stores::with([
                'requisition' => function($q) use ($between) {
                    $q->where('_status', '>', 1)
                      ->whereBetween('created_at', $between);
                },
                'requisition.user',
                'requisition.status',
                'requisition.bodie'
            ])
            ->withCount([
                'requisition as requisition_count' => function($q) use ($between) {
                    $q->where('_status', '>', 1)
                      ->whereBetween('created_at', $between);
                }
            ])
            ->whereHas('requisition',function($q) use ($between) {
                $q->where('_status', '>', 1)
                  ->whereBetween('created_at', $between);
            } )
            ->WhereNotIn('id',[14,15,19])->get();

            $status = RequisitionState::all();
            $res = [
                "stores"=>$requisition,
                "status"=>$status
            ];

        return response()->json($res);
    }

    public function getRequisition(Request $request){
        $store = $request->route('id');
        $req = $request->route('req');
        $requisition = Requisition::with('stores','bodie','user')->where([['_stores',$store],['id',$req]])->first();
        return response()->json($requisition);
    }

    public function createRequisition(Request $request){
        $insert = new Requisition();
        $insert->notes = $request->notes;
        $insert->_stores = $request->_stores;
        $insert->print = 0;
        $insert->_status = 1;
        $insert->_user = $request->_user;
        $insert->save();
        $res = $insert->fresh();
        return response()->json($res,200);

    }

    public function finishRequisition(Request $request){
        $products = $request->products;
        $requisitionBodie = $request->id;
        foreach($products as $req){
            $insert = new RequisitionBodies();
            $insert->_requisition = $requisitionBodie;
            $insert->code  = $req['code'];
            $insert->description = $req['description'];
            $insert->amount = $req['amount'];
            $insert->save();
        }
        $requisition = Requisition::find($requisitionBodie);
        $requisition->load('bodie','stores');
        $print = $this->printRequisition($requisition);
        if($print){
            $requisition->print = 1;
            $requisition->_status = 2;
            $requisition->save();
            $requisition->load('bodie','stores');
            return response()->json($requisition,200);
        }else{
            return response()->json($requisition,200);
        }
    }

    public function printReq(Request $request){
        $store = $request->route('id');
        $req = $request->route('req');
        $requisition = Requisition::with('stores','bodie','user')->where([['_stores',$store],['id',$req]])->first();
        $print = $this->printRequisition($requisition);
        return response()->json($requisition);
    }

    public function changeStatus(Request $request){
        $store = $request->route('id');
        $req = $request->route('req');

        $requisition = Requisition::with('stores','bodie','user')->where([['_stores',$store],['id',$req]])->first();
        $requisition->_status =  $requisition->_status + 1;
        $requisition->save();
        $res = $requisition->load('stores','bodie','user','status');
        return response()->json($requisition);
    }

    public function printRequisition($requisition){
        $print  = env('PRINT_INS');
        $in_coming = null;
        $connector = new NetworkPrintConnector($print, 9100);
        $printer = new Printer($connector);
        if($printer){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1,2);
            $printer->text("Pedido para: \n");
            $printer->setTextSize(2,2);
            $printer->text($requisition->stores['name']." \n");
            $printer->setTextSize(1,1);
            $printer->text("Creador : ".$requisition->user['complete_name']." \n");
            $printer->text("----------------------------------------\n");
            $created_at = is_null($in_coming) ? date('d/m/Y H:i', time()) : $requisition->created_at;
            $printer->text(" Fecha/Hora: ".$created_at." \n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("----------------------------------------\n");
            foreach($requisition->bodie as $product){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setFont(Printer::FONT_B);
                $printer->setTextSize(3,1);
                $printer->text($product['code']);
                $printer->setEmphasis(true);
                $printer->setTextSize(1,1);
                $printer->text("->".$product['description']." \n");
                // $printer->text($product['description']."\n");
                $printer->setFont(Printer::FONT_A);
                // $printer->setReverseColors(true);
                $printer->text("( ".$product['amount']." pz )");
                // $printer->setReverseColors(false);
                $printer->setEmphasis(false);
                $printer->feed(3);
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->feed(1);
            $printer->setTextSize(1,1);
            $printer->text($requisition->id."\n");
            $printer->text("GRUPO VIZCARRA\n");
            $printer->feed(1);
            $printer->cut();
            $printer->close();
            return true;
        }else{
            return false;
        }
    }

}
