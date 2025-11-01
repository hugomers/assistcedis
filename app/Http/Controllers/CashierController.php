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
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer as EscposPrinter;


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
        $print = $form['print'] ?? null;
        if($request->hasFile('current_cut')){
            $file = $request->file('current_cut');
            $uniqueName  =  uniqid() . '.' . $file->getClientOriginalExtension();
            $folderPath = 'public/uploads/cuts/' . $form['_store'];
            $file->storeAs($folderPath, $uniqueName); // lo abres vato
            $form['current_cut'] = $uniqueName;
        }
        // $opening = new Opening($form);
        // if($opening->save()){
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
                // return $openBox;
                if($openBox->status() == 201){
                    $opening = new Opening($form);
                    $opening->details_cut = json_encode($openBox->json());
                    $opening->save();
                    return response()->json('La Caja a sido Abierta',200);
                }else{
                    return response()->json($openBox->json(),$openBox->status());
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
                if($openBox->status() == 201){
                    $respuesta = $openBox->json();
                    $opening = new Opening($form);
                    $opening->withdrawal_original_mount = $respuesta['monto_original'];
                    $opening->details_cut = json_encode($respuesta['corte']);
                    $opening->save();
                    return response()->json('La Retirada se modifico con exito',200);
                }else{
                    return response()->json($openBox->json(),$openBox->status());
                }
            }else{
                return response()->json('No existe el tipo de Apertur',401);
            }
        // }else{
        //     return response()->json('No se pudo insertar la apertura',401);
        // }
    }

    public function getPrinter($id){
        $printers = Printer::where('_store',$id)->get();
        return response()->json($printers,200) ;
    }

    public function getCutsBoxes($sid){
        $opens = Opening::with(['createdby','cashier','type','store'])->where('_store',$sid)->whereDate('created_at','>=','2025-06-05')->get();
        $printers = Printer::where('_store',$sid)->get();
        $res = [
            "opens"=>$opens,
            "prints"=>$printers
        ];
        return response()->json($res);
    }

    public function getCurrenCut(Request $request){
        $opens = Opening::with(['createdby','cashier','type'])->where('id',$request->id)->first();
        $print = $request->print;
        $printed = $this->printCut(json_decode($opens->details_cut, true),$print,$opens->store['name']);
        return $printed;
    }

    public function printCut($header,$print,$store){
        // return $header->toCollect();
        $connector = new NetworkPrintConnector($print, 9100, 3);
        if($connector){
            $printer = new EscposPrinter($connector);
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text("           --REIMPRESION DE CORTE--           \n");
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("CIERRE DE TERMINAL"." \n");
            $printer->text($store." \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("Terminal: ".$header['corte']['DESTER']." \n");
            $printer->text("Fecha: ".$header['corte']['FECHA']." \n");
            $printer->text("Hora: ".$header['corte']['HORA']." \n");
            $printer->selectPrintMode(EscposPrinter::MODE_FONT_B);
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad("Saldo Inicial: ", 47).str_pad(number_format(floatval($header['corte']['SINATE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ventas efectivo: ", 47).str_pad(number_format(floatval($header['corte']['VENTASEFE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ingresos de efectivo: ", 47).str_pad(number_format(floatval($header['corte']['INGRESOS']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Retiradas de efectivo: ", 47).str_pad(number_format(floatval($header['corte']['RETIRADAS']) * -1 ,2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $efetot = (floatval($header['corte']['VENTASEFE']) +  floatval($header['corte']['INGRESOS']) +   floatval($header['corte']['SINATE']) ) - floatval($header['corte']['RETIRADAS']);
            // $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format((  floatval($cuts['VENTASEFE']) - floatval($cuts['RETIRADAS'])   + floatval($cuts['SINATE'])  ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format($header['totalEfe'],2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Declaracion de efectivo: ", 47).str_pad(number_format(floatval($header['corte']['EFEATE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Descuadre: ", 47). str_pad(number_format((floatval($header['corte']['EFEATE']) -  $header['totalEfe'] ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Importe Pendiente Cobro: ", 47).str_pad(number_format(floatval($header['corte']['IMPDC']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(" \n");
            $printer->text("Ingresos de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['ingresos']) > 0){
                foreach($header['ingresos'] as $ingreso){
                    $textoCortos = mb_strimwidth($ingreso['CONING'], 0, 40, "...");
                    $printer->text(str_pad($textoCortos, 47).str_pad(number_format(floatval($ingreso['IMPING']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Retiradas de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['retiradas']) > 0){
                foreach($header['retiradas'] as $retirada){
                    $textoCortod = mb_strimwidth($retirada['CONRET'], 0, 40, "...");
                    $printer->text(str_pad($textoCortod, 47).str_pad(number_format(floatval($retirada['IMPRET']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Vales Creados:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['vales']) > 0){
                foreach($header['vales'] as $vale){
                    $textoCorto = mb_strimwidth($vale['OBSANT'], 0, 40, "...");
                    $printer->text(str_pad($textoCorto, 47).str_pad(number_format(floatval($vale['IMPANT']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Desglose por forma de pago:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['totales']) > 0){
                foreach($header['totales'] as $pagos){
                    $textoCortoF = mb_strimwidth($pagos['CPTLCO'], 0, 40, "...");
                    $printer->text(str_pad($textoCortoF, 47).str_pad(number_format(floatval($pagos['IMPORTE']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Desglose de otros cobros de documentos:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->setJustification(EscposPrinter::JUSTIFY_RIGHT);
            $printer->text("Total Cobros: 0.00"." \n");
            $printer->text(" \n");
            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $printer->text("Detalle de operaciones:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['movimientos']) > 0){
                $printer->setJustification(EscposPrinter::JUSTIFY_RIGHT);
                $printer->text("N. de operaciones: ". number_format(floatval($header['movimientos']['MOVIMIENTOS']),2)." \n");
                $printer->text("Total de operaciones: ".number_format(floatval($header['movimientos']['TOTAL']),2) ." \n");
            }
            $printer->text(" \n");

            $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
            $printer->text("Detalle de monedas y billetes:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad('   2: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO0ATE']),5) . str_repeat(' ', 20) . '  5: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI6ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('   1: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO1ATE']),5) . str_repeat(' ', 20) . ' 10: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI5ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.50: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO2ATE']),5) . str_repeat(' ', 20) . ' 20: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI4ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.20: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO3ATE']),5) . str_repeat(' ', 20) . ' 50: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI3ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.10: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO4ATE']),5) . str_repeat(' ', 20) . '100: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI2ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.05: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO5ATE']),5) . str_repeat(' ', 20) . '200: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI1ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.02: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO6ATE']),5) . str_repeat(' ', 20) . '500: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI0ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.01: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO7ATE']),5) . str_repeat(' ', 35) , 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(" \n");
            $printer->cut();
            $printer->close();
            return true;
        }else{
            return "No se pudo imprimir";
        }
    }

    public function getDependients($sid){
        $opens = Staff::where([['_store',$sid],['acitve',1]])->get();
        return response()->json($opens);
    }
}
