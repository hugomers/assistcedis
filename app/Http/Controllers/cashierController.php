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
        $form = $request->all();
        // $print = $form['print'] ?? null;
        if($request->hasFile('current_cut')){
            $file = $request->file('current_cut');
            $uniqueName  =  uniqid() . '.' . $file->getClientOriginalExtension();
            $folderPath = 'public/uploads/cuts/' . $form['_store'];
            $file->storeAs($folderPath, $uniqueName); // lo abres vato
            $form['current_cut'] = $uniqueName;
        }
        $opening = new Opening($form);
        if($opening->save()){
            $tipo = $form['_type'];
            $store = Stores::find($form['_store']);
            $solicita = Staff::find($form['_created_by']);
            $cajero = Staff::find($form['_cashier']);
            $ip = $store->ip_address;
            // $ip = "192.168.10.160:1619";
            if($tipo == 1 || $tipo == 2){//descuadre
                $dat = [
                    "_cash"=>intval($form['cash'])
                ];
                $openBox =Http::post($ip.'/storetools/public/api/Cashier/opencashier',$dat);
                if($openBox->status() == 201){
                    $opening->details_cut = json_encode($openBox->json());
                    $opening->save();
                    return response()->json('La Caja a sido Abierta',200);
                }else{
                    return response()->json('Hubo un error en la apertura de la caja',401);
                };
            }else if($tipo == 3){//retirada
                $nuevomont = isset($form['withdrawal_mount']) ? $form['withdrawal_mount'] : null;
                $dat = [
                    "montonuevo"=>$nuevomont,
                    "retirada"=>$form['withdrawal_number'],
                    "_cash"=>$form['cash'],
                    "print"=>$print,
                ];
                $openBox =Http::post($ip.'/storetools/public/api/Cashier/changewithdrawal',$dat);
                $status = $openBox->status();
                if($status == 201){
                    $respuesta = $openBox->json();
                    $opening->withdrawal_original_mount = $respuesta['monto_original'];
                    $opening->details_cut = json_encode($respuesta['corte']);
                    $opening->save();
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
