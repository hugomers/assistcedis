<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stores;
use App\Models\Deposit;
use App\Models\DepositState;
use App\Models\Warehouses;
use Illuminate\Support\Facades\Http;
use App\Models\TransferBodies;
use Illuminate\Support\Facades\DB;

class DepositsController extends Controller
{

    public function getForms(Request $request){
        $fechas = $request->filt;
        if(isset($fechas['from'])){
            $desde = $fechas['from'];
            $hasta = $fechas['to'];
        }else{
            $desde = $fechas;
            $hasta = $fechas;
        }
        $deposits = Deposit::with(['store','status'])->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])->get();
        $statusses = DepositState::all();
        $stores  = Stores::all();
        return response()->json(["deposit"=>$deposits,"status"=>$statusses,"stores"=>$stores],200);
    }

    public function getFormsStore(Request $request){
        $fechas = $request->filt;
        if(isset($fechas['from'])){
            $desde = $fechas['from'];
            $hasta = $fechas['to'];
        }else{
            $desde = $fechas;
            $hasta = $fechas;
        }
        $sid = $request->route('sid');
        $deposits = Deposit::with(['store','status'])->where('_store',$sid)->whereBetween(DB::raw('DATE(created_at)'), [$desde, $hasta])->get();
        $statusses = DepositState::all();
        return response()->json(["deposit"=>$deposits,"status"=>$statusses],200);
    }

    public function newForm(Request $request){
        if ($request->hasFile('file_0')) {
            $file = $request->file('file_0');
            $filePath = $file->store('uploads', 'public');

            $insertdem = new Deposit;
            $insertdem->_store=$request->_store;
            $insertdem->import=$request->amount;
            $insertdem->_status=1;
            $insertdem->send_by=$request->send_by;
            $insertdem->refernce=$request->reference;
            $insertdem->picture=$filePath;
            $insertdem->concept=$request->concepto;
            $insertdem->origin=$request->origin;
            $insertdem->destiny=$request->destiny;
            $insertdem->save();
            $insertdem->load(['store','status']);

            return response()->json([
                'message' => 'Formulario y archivo subidos exitosamente',
                'insert' => $insertdem
            ]);
        }

        return response()->json(['message' => 'No se subió ningún archivo'], 400);
    }

    public function changeStatus(Request $request){
        $id = $request->id;
        $status = $request->status;

        $deposit = Deposit::find($id);
        $deposit->_status = $status;
        $deposit->save();
        $deposit->load(['store','status']);

        return response()->json($deposit,200);

    }

    public function changeTicket(Request $request){
        $id = $request->id;
        $ticket = $request->ticket;

        $deposit = Deposit::find($id);
        $deposit->ticket = $ticket;
        $deposit->save();
        $deposit->load(['store','status']);
        return response()->json($deposit,200);

    }

}
