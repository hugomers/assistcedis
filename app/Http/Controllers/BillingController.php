<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Http;
use App\Models\Stores;
use Illuminate\Support\Facades\DB;
use App\Models\Billing;

use App\Models\UseCfdi;

class BillingController extends Controller
{

    public function index(){
        $res = [
            "stores"=>Stores::all(),
            "cfdi"=>UseCfdi::all(),
        ];
        return response()->json($res,200);
    }

    public function validTicket(Request $request){
        $workpoint = $request->workpoint;
        $folio = $request->folio;

        $store = Stores::find($workpoint);
        if($store){
            $billing = Billing::where([['ticket',$folio],['_store',$workpoint]])->first();
            if($billing){
                $billing->load('payments','logs.user');
                return response()->json($billing,403);
            }else{
                $ticket = http::post($store['ip_address'].'/storetools/public/api/billing/validateTck',["folio"=>$folio]);
                return response()->json($ticket->json(), $ticket->status());
            }
        }else{
            return response()->json(["message"=>'La sucursal no existe'],404);
        }
    }

    public function readRFC(Request $request){
        // return $request->all();
        $servfac = env('SERVER_FAC');
        $store = Stores::find($request->store);
        // return $store;/
        if($store){
            $dat = [
                "firebird"=>$store->firebird,
                "rfc"=>$request->rfc,
            ];
            $getClient = http::post($servfac.'readRFC',$dat);
            $cliente = $getClient->json();
            return response()->json($cliente,200);

        }else{
            return response()->json(["message"=>"No se encontro la solicitud"],404);
        }
    }

    public function readConstancy(Request $request){
        $request->validate([
            'file' => 'required|mimes:pdf'
        ]);

        $parser = new Parser();
        $pdf = $parser->parseFile($request->file('file')->path());

        $text = $pdf->getText();
        // return $text;
        $res = [
            "rfc"=> $this->extraer($text, 'RFC:'),
            "nombre"=> $this->separarMayusculas($this->extraer($text, 'NombreComercial:')),
            "razonSocial"=> $this->separarMayusculas($this->extraer($text, 'Denominación/RazónSocial:')),
            "cp"=>$this->extraer($text, 'CódigoPostal:'),
            "calle"=>$this->extraer($text, 'NombredeVialidad:'),
            "numExt"=>$this->extraer($text, 'NúmeroExterior:'),
            "numInt"=>$this->extraer($text, 'NúmeroInterior:'),
            "colonia"=>$this->extraer($text, 'NombredelaColonia:'),
            "municipio"=>$this->extraer($text, 'NombredelaLocalidad:'),
            "entFederativa"=>$this->extraer($text, 'NombredelaEntidadFederativa:'),
        ];
        return response()->json($res);
    }

    private function extraer($texto, $inicio){
        $posIni = strpos($texto, $inicio);
        if ($posIni === false) return null;
        $posIni += strlen($inicio);
        while (isset($texto[$posIni]) && ($texto[$posIni] === "\t" || $texto[$posIni] === " ")) {
            $posIni++;
        }
       $regex = '/[A-Za-zÁÉÍÓÚÑáéíóú0-9\/]+(?:[A-Za-zÁÉÍÓÚÑáéíóú0-9\/])*:/u';
        if (preg_match($regex, $texto, $match, PREG_OFFSET_CAPTURE, $posIni)) {
            $posFin = $match[0][1];
        } else {
            $posFin = strlen($texto);
        }
        $valor = substr($texto, $posIni, $posFin - $posIni);
        $valor = str_replace(["\t", "\r", "\n"], " ", $valor);
        $valor = preg_replace('/\s+/', ' ', $valor);
        return trim($valor);
    }
    private function separarMayusculas($text){
        return preg_replace('/([A-ZÁÉÍÓÚÑ]+)([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)/', '$1 $2', $text);
    }

    public function sendBilling(Request $request){
        $ins = [
            "_store"=>$request->store['id'],
            "ticket"=>$request->folio,
            "total"=>$request->total,
            "_state"=>1,
            "_cfdi"=>$request->cfdi['id'],
            "notes"=>isset($request->notes) ? $request->notes : null,
            "name"=>$request->nombre,
            "email"=>$request->email,
            "celphone"=>$request->telefono,
            "rfc"=>$request->rfc,
            "razon_social"=>$request->razonSocial,
            "address"=>json_encode($request->address),
        ];
        $insert = Billing::create($ins);
        $insert->save();
        if($insert){
            $insert->fresh();
            $insert->payments()->createMany($request->payments);
            $log = $this->log($insert, 1, 24);
            if($log){
                $insert->load('payments','logs.user');
                return response()->json($insert,200);
            }else{
                return response()->json(["message"=>"Hay un problema con el log"],500);
            }
        }else{
            return response()->json(["message"=>"No se logro crear la factura"],500);
        }
    }

    private function log(Billing $billing,$status, $user ){
        switch ($status) {
            case 1:
                $billing->logs()->create([
                    '_state'=>$status,
                    '_user' => $user,
                    'details' => json_encode(["details" => 'Formulario Creado']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            case 2:
                $billing->logs()->create([
                    '_state'=>$status,
                    '_user' => $user,
                    'details' => json_encode(["details" => 'Solicitud aceptada']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            case 3:
                $billing->logs()->create([
                    '_state'=>$status,
                    '_user' => $user,
                    'details' => json_encode(["details" => 'Solicitud Pausada']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            case 4:
                $billing->logs()->create([
                    '_state'=>$status,
                    '_user' => $user,
                    'details' => json_encode(["details" => 'Solicitud Terminada']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            case 5:
                $billing->logs()->create([
                    '_state'=>$status,
                    '_user' => $user,
                    'details' => json_encode(["details" => 'Solicitud Cancelada']),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                return true;
            default:
            return false;
        }
    }

    public function nextState(Request $request){
        $billing = Billing::find($request->billing['id']);
        $status = $billing->_state + 1;
        $usr = $request->user;
        if($billing){
            $log = $this->log($billing,$status,$usr);
            if($log){
                $billing->_state = $status;
                $billing->save();
                $billing->fresh();
                $billing->load(['logs.user','payments','store','status','cfdi']);
                return response()->json($billing,200);
            }else{
                return response()->json(['message'=>'No se realizo el log'],404);
            }
        }else{
            return response()->json(['message'=>'No se entontro el pedido'],404);
        }

    }

    public function getBillings(Request $request){
        $fechas = $request->date;
        if(isset($fechas['from'])){
            $from = $fechas['from'];
            $to = $fechas['to'];
        }else{
            $from = $fechas;
            $to = $fechas;
        }

        $billings = Billing::with(['logs.user','payments','store','status'])
        ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
        ->get();
        return response()->json($billings);
    }

    public function getBilling(Request $request){
        $id = $request->id;
        $billing = Billing::find($id);
        if($billing){
            $billing->load(['logs.user','payments','store','status','cfdi']);
            $store = Stores::find($billing->_store);
            if($store){
                $ticket = http::post($store['ip_address'].'/storetools/public/api/billing/getTckBilling',["folio"=>$billing->ticket]);
                if($ticket->status()== 201 ){
                    $billing->ticketSuc = $ticket->json();
                    // return $billing;
                    $servfac = env('SERVER_FAC');
                    $resources = http::post($servfac.'getServerFac',$billing->toArray());
                    if($resources->status() == 200){
                        return response()->json($resources->json(),200);
                    }else{
                        return response()->json(["message"=>"Hubo un problema al buscar datos"],500);
                    }
                }else{
                    return response()->json(["message"=>"No se encontro el ticket en la sucursal"],404);
                }
            }return response()->json(["message"=>"No se encontro la sucursal"],404);
        }else{
            return response()->json(["message"=>"No se encontro la solicitud"],404);
        }
    }

    public function getFolio(Request $request){
        $servfac = env('SERVER_FAC');
        $dat = [
            "firebird"=>$request->store['firebird'],
            "prefix"=> $request->store['prefix'],
        ];
        $resources = http::post($servfac.'getFolio',$request->all());
        $folio = str_pad($resources->json()+1,10,'0',STR_PAD_LEFT);
        $clave = $request->store['prefix'].$folio;
        return response()->json($clave,200);
    }


    public function crearFacturaInterna(Request $request){
        $servfac = env('SERVER_FAC');
        $resources = http::post($servfac.'crearFacturaInterna',$request->all());
        return $resources;
        $clave = $resources->json();
        return response()->json($clave,200);
    }

    public function finishState(Request $request){
        $billing = Billing::find($request->billing['id']);
        $status = $billing->_state + 2;
        $usr = $request->user;
        if($billing){
            $log = $this->log($billing,$status,$usr);
            if($log){
                $billing->_state = $status;
                $billing->invoice = $request->billing['invoice'];
                $billing->save();
                $billing->fresh();
                $billing->load(['logs.user','payments','store','status','cfdi']);
                return response()->json($billing,200);
            }else{
                return response()->json(['message'=>'No se realizo el log'],404);
            }
        }else{
            return response()->json(['message'=>'No se entontro el pedido'],404);
        }
    }

}
