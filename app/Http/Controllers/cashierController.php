<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Staff;
use App\Models\Opening;
use App\Models\OpeningType;
use App\Models\Stores;
use Illuminate\Support\Facades\Http;
use App\Models\Printer;


class CashierController extends Controller
{
    public function getStaff($id){
        $staff = Staff::where([['_store',$id], ['_position',11]])->get();
        return $staff;
    }

    public function AddFile(Request $request){
        if($request->hasFile('files')){
            $file = $request->file('files');
            $fileName = $request->__key; // Obtén el nombre de archivo desde la solicitud
            $file->storeAs('uploads', $fileName); // Almacena el archivo en la carpeta "uploads"
            return $request->__key;
        } else {
            return 'err';
        }
    }

    public function Opening(Request $request){
        $opening = Opening::insert($request->all());
        // $opening = true;
        if($opening){
            $tipo = $request->_type;
            $store = Stores::find($request->_store);
            $solicita = Staff::find($request->_created_by);
            $cajero = Staff::find($request->_cashier);
            $ip = $store->ip_address;
            // $ip = "192.168.10.112:1619";
            if($tipo == 1 || $tipo == 2){//descuadre
                $dat = [
                    "_cash"=>$request->cash
                ];
                $opening =Http::post($ip.'/storetools/public/api/Cashier/opencashier',$dat);
                $status = $opening->status();
                if($status == 201){
                    return response()->json('La Caja a sido Abierta',200);
                }else{
                    return response()->json('Hubo un error en la apertura de la caja',401);
                };
            }else if($tipo == 3){//retirada
                $dat = [
                    "montonuevo"=>$request->withdrawal_mount,
                    "retirada"=>$request->withdrawal_number,
                    "_cash"=>$request->cash
                ];
                $opening =Http::post($ip.'/storetools/public/api/Cashier/changewithdrawal',$dat);
                $status = $opening->status();
                if($status == 201){
                    return response()->json('La Retirada se modifico con exito',200);
                }else{
                    return response()->json('Hubo un error en la modificacion de la retirada',401);
                }

            }else{
                return response()->json('No existe el tipo de Apertur',401);
            }
        }else{
            return response()->json('No se pudo insertar la apertura',401);
        }
    }

    public function getPrinter($id){
        $printers = Printer::where('_store',$id)->get();
        return response()->json($printers,200) ;
    }
}
