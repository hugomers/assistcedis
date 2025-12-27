<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Http;
use App\Models\Stores;
use Illuminate\Support\Facades\Storage;
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
            if (!empty($cliente['client']['REG_FISC'])) {
                $catalogo = $this->catalogoRegimenes();
                $cliente['client']['regimen'] = $this->regimen($catalogo[$cliente['client']['REG_FISC']]) ?? null;
                } else {
                $cliente['client']['regimen'] = null;
            }
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
        $regimenTexto = $this->extraerRegimen($text);
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
            "regimen"=>$this->regimen($text)
        ];
        return response()->json($res);
    }
    private function extraerRegimen(string $texto): ?string{
        if (!str_contains($texto, 'Regímenes')) {
            return null;
        }
        if (preg_match(
            '/Regímenes[\s\S]*?(Régimen\s*de\s*las\s*Personas[\s\S]*?)(\d{2}\/\d{2}\/\d{4})/u',
            $texto,
            $m
        )) {
            $regimen = trim($m[1]);
            $regimen = preg_replace('/\s+/', ' ', $regimen);
            return $regimen;
        }

        return null;
    }
    private function regimen(?string $regimenPdf): ?array{
        if (!$regimenPdf) return null;

        $regimenPdfNorm = $this->normalizar($regimenPdf);

        foreach ($this->catalogoRegimenes() as $clave => $descripcion) {
            if (
                str_contains(
                    $this->normalizar($descripcion),
                    $regimenPdfNorm
                ) || str_contains(
                    $regimenPdfNorm,
                    $this->normalizar($descripcion)
                )
            ) {
                return [
                    'clave' => $clave,
                    'descripcion' => $descripcion
                ];
            }
        }

        return null;
    }
    private function catalogoRegimenes(): array{
        return [
            601 => 'REGIMEN GENERAL DE LEY PERSONAS MORALES',
            602 => 'RÉGIMEN SIMPLIFICADO DE LEY PERSONAS MORALES',
            603 => 'PERSONAS MORALES CON FINES NO LUCRATIVOS',
            604 => 'RÉGIMEN DE PEQUEÑOS CONTRIBUYENTES',
            605 => 'RÉGIMEN DE SUELDOS Y SALARIOS E INGRESOS ASIMILADOS A SALARIOS',
            606 => 'RÉGIMEN DE ARRENDAMIENTO',
            607 => 'RÉGIMEN DE ENAJENACIÓN O ADQUISICIÓN DE BIENES',
            608 => 'RÉGIMEN DE LOS DEMÁS INGRESOS',
            609 => 'RÉGIMEN DE CONSOLIDACIÓN',
            610 => 'RÉGIMEN RESIDENTES EN EL EXTRANJERO SIN ESTABLECIMIENTO PERMANENTE EN MÉXICO',
            611 => 'RÉGIMEN DE INGRESOS POR DIVIDENDOS (SOCIOS Y ACCIONISTAS)',
            612 => 'RÉGIMEN DE LAS PERSONAS FÍSICAS CON ACTIVIDADES EMPRESARIALES Y PROFESIONALES',
            613 => 'RÉGIMEN INTERMEDIO DE LAS PERSONAS FÍSICAS CON ACTIVIDADES EMPRESARIALES',
            614 => 'RÉGIMEN DE LOS INGRESOS POR INTERESES',
            615 => 'RÉGIMEN DE LOS INGRESOS POR OBTENCIÓN DE PREMIOS',
            616 => 'SIN OBLIGACIONES FISCALES',
            617 => 'PEMEX',
            618 => 'RÉGIMEN SIMPLIFICADO DE LEY PERSONAS FÍSICAS',
            619 => 'INGRESOS POR LA OBTENCIÓN DE PRÉSTAMOS',
            620 => 'SOCIEDADES COOPERATIVAS DE PRODUCCIÓN QUE OPTAN POR DIFERIR SUS INGRESOS.',
            621 => 'RÉGIMEN DE INCORPORACIÓN FISCAL',
            622 => 'RÉGIMEN DE ACTIVIDADES AGRÍCOLAS, GANADERAS, SILVÍCOLAS Y PESQUERAS PM',
            623 => 'RÉGIMEN DE OPCIONAL PARA GRUPOS DE SOCIEDADES',
            624 => 'RÉGIMEN DE LOS COORDINADOS',
            625 => 'RÉGIMEN DE LAS ACTIVIDADES EMPRESARIALES CON INGRESOS A TRAVÉS DE PLATAFORMAS TECNOLÓGICAS.',
            626 => 'RÉGIMEN SIMPLIFICADO DE CONFIANZA',
        ];
    }
    private function normalizar(string $texto): string{
        $texto = mb_strtoupper($texto, 'UTF-8');

        $texto = strtr($texto, [
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]);

        $texto = preg_replace('/[^A-Z ]/', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
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

    // public function sendBilling(Request $request){
    //     $ins = [
    //         "_store"=>$request->store['id'],
    //         "ticket"=>$request->folio,
    //         "total"=>$request->total,
    //         "_state"=>1,
    //         "_cfdi"=>$request->cfdi['id'],
    //         "notes"=>isset($request->notes) ? $request->notes : null,
    //         "name"=>$request->nombre,
    //         "email"=>$request->email,
    //         "celphone"=>$request->telefono,
    //         "rfc"=>$request->rfc,
    //         "razon_social"=>$request->razonSocial,
    //         "address"=>json_encode($request->address),
    //     ];
    //     $insert = Billing::create($ins);
    //     $insert->save();
    //     if($insert){
    //         $insert->fresh();
    //         $insert->payments()->createMany($request->payments);
    //         $log = $this->log($insert, 1, 24);
    //         if($log){
    //             $insert->load('payments','logs.user');
    //             return response()->json($insert,200);
    //         }else{
    //             return response()->json(["message"=>"Hay un problema con el log"],500);
    //         }
    //     }else{
    //         return response()->json(["message"=>"No se logro crear la factura"],500);
    //     }
    // }

    public function sendBilling(Request $request){
        DB::beginTransaction();

        try {
            $payments = json_decode($request->payments, true);
            $address  = json_decode($request->address, true);
            $regimen  = json_decode($request->regimen, true);
            $insert = Billing::create([
                "_store"        => $request->store,
                "ticket"        => $request->folio,
                "total"         => $request->total,
                "_state"        => 1,
                "_cfdi"         => $request->cfdi,
                "notes"         => $request->notes ?? null,
                "name"          => $request->nombre,
                "email"         => $request->email,
                "celphone"      => $request->telefono,
                "rfc"           => $request->rfc,
                "razon_social"  => $request->razonSocial,
                "address"       => json_encode($address),
                "regimen"       => json_encode($regimen),
            ]);
            if (!empty($payments)) {
                $insert->payments()->createMany($payments);
            }

            if ($request->hasFile('constancia')) {
                $file = $request->file('constancia');
                $fileName = $request->rfc . '.' . $file->getClientOriginalExtension();
                $path = "vhelpers/Constances/{$insert->id}/{$fileName}";
                Storage::put($path, file_get_contents($file));
                $insert->update([
                    'constancia' => $fileName
                ]);
            }
            if (!$this->log($insert, 1, 24)) {
                throw new \Exception('Error al generar log');
            }
            DB::commit();
            $insert->load('payments', 'logs.user');
            return response()->json($insert, 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la solicitud',
                'error'   => $e->getMessage()
            ], 500);
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
        $resources = http::post($servfac.'crearFacturaInterna',$request->billing);
        if($resources->status()==201){
            $dataresp = $resources->json();
            $billing = Billing::find($request->billing['id']);
            $status = $billing->_state + 1;
            $usr = $request->user;
            if($billing){
                $log = $this->log($billing,$status,$usr);
                if($log){
                    $billing->_state = $status;
                    $billing->invoice = $dataresp['data']['cve_doc'];
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
        return response()->json($resources->json(),$resources->status());
    }

    public function finishState(Request $request){
        $billing = Billing::find($request->billing['id']);
        $status = $billing->_state + 2;
        $usr = $request->user;
        if($billing){
            $log = $this->log($billing,$status,$usr);
            if($log){
                $billing->_state = $status;
                // $billing->invoice = $request->billing['invoice'];
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
