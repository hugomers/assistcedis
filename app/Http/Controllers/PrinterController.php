<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\EscposImage;
use App\Requisition;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use NumberFormatter;
use App\Models\Invoice;
use App\Models\OrderVA;

class PrinterController extends Controller
{

    public function printck($sale,$payments){
        try{
            $connector = new NetworkPrintConnector($sale['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
            try {
                try{
                    $imagen = "\\\\192.168.60.253\\c\\Users\\Administrador\\Documents\\TCKPHP.png";//poner en env el servidor de donde sale
                    $filtered = array_filter($payments, function($val) {
                        return isset($val['id']) && !is_null($val['id'])  && $val['val'] > 0;
                    });
                    if ( isset($payments['conditions']['super']) && $payments['conditions']['super']){
                        $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $sale['total'];
                        if($cambio > 0){
                            foreach ($filtered as $key => &$payment) {
                                if (isset($payment['id']['id']) && $payment['id']['id'] === 5 ) {
                                    $original = floatval($payment['val']);
                                    $adjusted = $original - $cambio;
                                    $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                                    $descontado = true;
                                    break;
                                }
                            }
                        }
                    }
                    $headers = json_decode($sale['cashier']['cash']['tpv']['herader_tck']);
                    $footers = json_decode($sale['cashier']['cash']['tpv']['footer_tck']);
                    $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
                    $partes = explode('.', number_format($sale['total'], 2, '.', ''));
                    $pesos = (int)$partes[0];
                    $letrasPesos = ucfirst($formatter->format($pesos));
                    $totlet = "$letrasPesos pesos M.N.";

                    if(file_exists($imagen)){
                        $logo = EscposImage::load($imagen, false);
                        $printer->setJustification(Printer::JUSTIFY_CENTER);
                        $printer->bitImage($logo,0);
                        $printer->feed();
                    }
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text("------------------------------------------------\n");
                    $printer->text(" \n");
                    // $printer->selectPrintMode(Printer::MODE_FONT_B);
                    foreach($headers  as $header){
                        $printer->text($header->val."\n");
                    }
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text($sale['cashier']['cash']['store']['alias']."-".$sale['cashier']['cash']['name']." \n");
                    $printer->text("N° ".$sale['_cash']."-". str_pad($sale['document_id'], 6, "0", STR_PAD_LEFT)." Fecha: ".$sale["created_at"] ." \n");
                    $printer->text("Forma de Pago: ".mb_convert_encoding($sale["pfpa"]['name'],'UTF-8')." \n");
                    $printer->text("Cliente: ".mb_convert_encoding($sale["client_name"],'UTF-8')." \n");

                    // $printer->text("_______________________________________________ \n");
                    // $printer->text("ARTICULO        UD.        PRECIO        TOTAL \n");
                    // $printer->text("_______________________________________________ \n");
                    // $printer -> setFont(Printer::FONT_B);
                    // foreach($sale['bodie'] as $product){
                    //     $printer->setJustification(printer::JUSTIFY_LEFT);
                    //     $printer->text(mb_convert_encoding($product['code'], 'UTF-8')."   ".mb_convert_encoding($product['description'], 'UTF-8')." \n");
                    //     $printer->setJustification(printer::JUSTIFY_RIGHT);
                    //     $quantity = str_pad(number_format($product['amount'],2,'.',''),15);
                    //     $arti [] = $product['amount'];
                    //     $price = str_pad(number_format($product['price'],2,'.',''),15);
                    //     $total = str_pad(number_format($product['total'],2,'.',''),10);
                    //     $printer->text($quantity." ".$price."  ".$total." \n");
                    // }

                    // if($sale['iva'] == null){
                        $printer->text(str_repeat("_", 48) . "\n");
                        $printer->text(
                            str_pad("ARTICULO", 15) .
                            str_pad("UD.", 9) .
                            str_pad("PRECIO", 14) .
                            str_pad("TOTAL", 10). "\n"
                        );
                        $printer->text(str_repeat("_", 48) . "\n");
                    // }else{
                    //     $printer->text(str_repeat("_", 48) . "\n");
                    //     $printer->text(
                    //         str_pad("ARTICULO", 12) .
                    //         str_pad("UD.", 6) .
                    //         str_pad("PRECIO", 9) .
                    //         str_pad("IVA", 6) .
                    //         str_pad("S.TO", 7) .
                    //         str_pad("TOTAL", 7) . "\n"
                    //     );
                    //     $printer->text(str_repeat("_", 48) . "\n");
                    // }
                    $printer->setFont(Printer::FONT_B);
                    foreach ($sale['bodie'] as $product) {
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->text(mb_convert_encoding($product['code'], 'UTF-8') . "   " . mb_convert_encoding($product['description'], 'UTF-8') . " \n");
                        $printer->setJustification(Printer::JUSTIFY_RIGHT);
                        $impuesto = isset($product['iva']);
                        // $impuesto = false;
                        // $amount = str_pad(number_format($product['amount'], 2,'.',''),$impuesto ? 9 : 15);  // UD. (10)
                        $amount = str_pad(number_format($product['amount'], 2,'.',''), 15);  // UD. (10)
                        $arti [] = $product['amount'];
                        // $price  = str_pad(number_format($product['price'], 2,'.',''),$impuesto ? 11 : 18);   // PRECIO (15)
                        $price  = str_pad(number_format($product['price'], 2,'.',''), 18);   // PRECIO (15)
                        // $importeIva = $product['price'] * ($product['iva'] / 100);//IVA
                        // $iva      = $impuesto ?  str_pad(number_format($importeIva, 2,'.',''),9) : null;           // IVA (6)
                        // $subtotal = $impuesto ?  str_pad(number_format($product['subtotal'], 2,'.',''),9) : null; // S.TO (7)
                        $total  = str_pad(number_format($impuesto ? $product['subtotal'] : $product['total'] , 2,'.',''), 12);   // TOTAL (10)
                        // $impuesto ? $printer->text($amount . $price . $iva . $subtotal . $total . "\n") : $printer->text($amount . $price . $total . "\n" );
                        $printer->text($amount . $price . $total . "\n" );

                    }
                    $printer -> setFont(Printer::FONT_A);
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->setJustification(printer::JUSTIFY_RIGHT);
                    $printer->setEmphasis(true);
                    if(isset($sale['iva'])){
                        $printer->text("SUBTOTAL: ");
                        $printer->text(str_pad("$".number_format($sale["subtotal"],2),13, " " ,STR_PAD_LEFT)." \n");
                        $printer->text("IVA: ");
                        $importeTotalIva = $sale['subtotal'] * ($sale['iva'] / 100);
                        $printer->text(str_pad("$".number_format($importeTotalIva,2),13, " " ,STR_PAD_LEFT)." \n");
                    }
                    $printer->text("TOTAL: ");
                    $printer->text(str_pad("$".number_format($sale["total"],2),13, " " ,STR_PAD_LEFT)." \n");
                    $printer->text(" \n");
                    $printer->setEmphasis(false);
                    foreach($filtered as $pago){
                        $mosPag = $pago['id']['name'] == 'VALE' ? "VALE N. ". $sale['val_code']   : $pago['id']['name'];
                        $printer->text(mb_convert_encoding($mosPag,'UTF-8').": ");
                        // $printer->text(str_pad('',7,' '));
                        $printer->text(str_pad("$".number_format($pago['val'],2),13, " " ,STR_PAD_LEFT)." \n");
                    }
                    if($sale['change'] <> 0){
                        $printer->text("Cambio: ");
                        $printer->text(str_pad("$".number_format($sale['change'],2),13, " " ,STR_PAD_LEFT)." \n");
                    }
                    $printer->text($totlet." \n");
                    $printer->text(" \n");
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text(" \n");
                    $printer->text("N Articulos: ".array_sum($arti)." \n");
                    $printer->text(" \n");
                    $printer->text("Vendedor :".$sale['staff']['complete_name']." \n");
                    $printer->text("Cajero :".$sale['cashier']['user']['staff']['complete_name']." \n");
                    $printer->text(" \n");
                    $printer->text(isset($sale["obsertvations"]) ? $sale["obsertvations"] :"" ." \n");
                    // $printer->text("-------------------Grupo-Vizcarra---------------"." \n");
                    foreach($footers as $footer){
                        $printer->text(mb_convert_encoding($footer->val,'UTF-8')." \n");
                    }
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->text("--------------------------------------------\n");
                    $printer->setBarcodeHeight(50);
                    $printer->setBarcodeWidth(2);
                    $printer->barcode($sale['id']);
                    $printer->feed(1);
                    $printer->text("GRUPO VIZCARRA\n");
                    $printer->feed(1);
                    $printer -> cut();
                    $printer -> close();
                }catch(Exception $e){}
            } finally {
                $printer -> close();
                return true;
            }
                return false;
    }

    public function printret($header){
        try{
            $connector = new NetworkPrintConnector($header['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $headersTCK = json_decode($header['cashier']['cash']['tpv']['herader_tck']);
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                foreach($headersTCK  as $headerT){
                    $printer->text($headerT->val."\n");
                }
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("SALIDA DE TERMINAL".$header['_terminal']." \n");
                $printer->text("N° ".$header['fs_id']." Fecha: ".$header["created_at"] ." \n");
                $printer->text("creado Por :".$header["cashier"]['user']['staff']['complete_name']." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($header['provider']['name']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE RETIRADO: ",14));
                $printer->text(number_format($header['import'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['concept']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;
    }

    public function printing($header){
        try{
            $connector = new NetworkPrintConnector($header['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $headersTCK = json_decode($header['cashier']['cash']['tpv']['herader_tck']);
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                foreach($headersTCK  as $headerT){
                    $printer->text($headerT->val."\n");
                }
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("ENTRADA DE TERMINAL".$header['cashier']['cash']['_terminal']." \n");
                $printer->text("N° ".$header['fs_id']." Fecha: ".$header["created_at"] ." \n");
                $printer->text("creado Por :".$header["cashier"]['user']['staff']['complete_name']." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($header['client']['name']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE INGRESADO: ",14));
                $printer->text(number_format($header['import'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['concept']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;
    }

    public function printAdvance($header){
        try{
            $connector = new NetworkPrintConnector($header['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $headersTCK = json_decode($header['cashier']['cash']['tpv']['herader_tck']);
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                foreach($headersTCK  as $headerT){
                    $printer->text($headerT->val."\n");
                }
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("N° ".$header['fs_id']." Fecha: ".$header["created_at"] ." \n");
                $printer->text("creado Por :".$header["cashier"]['user']['staff']['complete_name']." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($header['client_name']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE VALE: ",14));
                $printer->text(number_format($header['import'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['observations']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;
    }

    public function printCut($header,$cash){
        try{
            $connector = new NetworkPrintConnector($cash['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text("           ---IMPRESION DE CORTE---           \n");
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("CIERRE DE TERMINAL"." \n");
            $printer->text($cash['store']['name']." \n");
            $printer->text($cash['cashier']['user']['staff']['complete_name']." \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("Terminal: ".$cash['name']." \n");
            $printer->text("Fecha: ".$cash['cashier']['close_date']." \n");
            // $printer->text("Hora: ".$cash['cashier']['close_date']." \n");
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad("Saldo Inicial: ", 47).str_pad(number_format(floatval($cash['cashier']['cash_start']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ventas efectivo: ", 47) . str_pad(number_format(floatval($header['fpa']->firstWhere('_payment', 2)?->total ?? 0), 2), 16, ' ', STR_PAD_LEFT) . " \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ingresos de efectivo: ", 47).str_pad(number_format(floatval($header['ingresos']),2), 16, ' ', STR_PAD_LEFT)." \n");
            // $printer->text(str_pad("Ingresos de efectivo: ", 47).str_pad(number_format(floatval(0),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Retiradas de efectivo: ", 47).str_pad(number_format(floatval($header['retiradas']) * -1 ,2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            // $efetot = (floatval($cuts['VENTASEFE']) +  floatval($cuts['INGRESOS']) +   floatval($cuts['SINATE']) ) - floatval($cuts['RETIRADAS']);
            // $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format((  floatval($cuts['VENTASEFE']) - floatval($cuts['RETIRADAS'])   + floatval($cuts['SINATE'])  ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format($header['efectivoencaja'],2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Declaracion de efectivo: ", 47).str_pad(number_format(floatval($header['totalDeclarado']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Descuadre: ", 47). str_pad(number_format((floatval($header['descuadre'])),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            // $printer->text(str_pad("Importe Pendiente Cobro: ", 47).str_pad(number_format(floatval($cuts['IMPDC']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(str_pad("Importe Pendiente Cobro: ", 47).str_pad(number_format(floatval(0),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(" \n");
            $printer->text("Ingresos de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($cash['cashier']['ingress']) > 0){
                foreach($cash['cashier']['ingress'] as $ingreso){
                    $textoCortos = mb_strimwidth($ingreso['concept'], 0, 40, "...");
                    $printer->text(str_pad($textoCortos, 47).str_pad(number_format(floatval($ingreso['import']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Retiradas de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($cash['cashier']['withdrawal']) > 0){
                foreach($cash['cashier']['withdrawal'] as $retirada){
                    $textoCortod = mb_strimwidth($retirada['concept'], 0, 40, "...");
                    $printer->text(str_pad($textoCortod, 47).str_pad(number_format(floatval($retirada['import']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Vales Creados:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($cash['cashier']['addvances']) > 0){
                foreach($cash['cashier']['addvances'] as $vale){
                    $textoCorto = mb_strimwidth($vale['observations'], 0, 40, "...");
                    $printer->text(str_pad($textoCorto, 47).str_pad(number_format(floatval($vale['import']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Desglose por forma de pago:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['fpa']) > 0){
                foreach($header['fpa'] as $pagos){
                    $textoCortoF = mb_strimwidth($pagos['payment']['name'], 0, 40, "...");
                    $printer->text(str_pad($textoCortoF, 47).str_pad(number_format(floatval($pagos['total']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Desglose de otros cobros de documentos:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text("Total Cobros: 0.00"." \n");
            $printer->text(" \n");


            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Detalle de operaciones:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($cash['cashier']['sale']) > 0){
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                $printer->text("N. de operaciones: ". number_format(floatval(count($cash['cashier']['sale'])),2)." \n");
                $totalOperaciones = array_reduce($header['fpa']->toArray(), function($carry, $item) {
                    return $carry + ($item['total'] ?? 0);
                }, 0);
                $printer->text("Total de operaciones: ".number_format(floatval($totalOperaciones),2) ." \n");
            }
            $printer->text(" \n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            // $printer->text("Detalle de monedas y billetes:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $monedas = $header['declarado']['modedas'];
            $billetes = $header['declarado']['billetes'];
            $maxRows = max(count($monedas), count($billetes));
            $printer->text("Detalle de monedas y billetes:\n");
            $printer->text(str_repeat("─", 64) . "\n");

            for ($i = 0; $i < $maxRows; $i++) {
                $moneda = $monedas[$i] ?? ['key' => '', 'val' => ''];
                $billete = $billetes[$i] ?? ['key' => '', 'val' => ''];
                $printer->text(
                    str_pad(
                        str_pad(number_format($moneda['key']), 4, ' ', STR_PAD_LEFT) . ": " .
                        str_repeat(' ', 1) . str_pad(floatval($moneda['val']), 5, ' ', STR_PAD_LEFT) .
                        str_repeat(' ', 20) .
                        str_pad(number_format($billete['key']), 4, ' ', STR_PAD_LEFT) . ": " .
                        str_repeat(' ', 1) . str_pad(floatval($billete['val']), 5, ' ', STR_PAD_LEFT),
                        64,
                        ' ',
                        STR_PAD_BOTH
                    ) . "\n"
                );
                // $printer->text(str_pad(str_pad($modeda['key'] < 0 ? number_format($moneda['key'],2) : number_format($moneda['key']), 4, ' ', STR_PAD_LEFT) .": ".str_repeat(' ', 1)  . str_pad(floatval($moneda['val'] ),5) . str_repeat(' ', 20) .str_pad(number_format($billete['key']), 4, ' ', STR_PAD_LEFT) .": ". str_repeat(' ', 1)  . str_pad(floatval($billete['val']),5), 64, ' ', STR_PAD_BOTH). "\n");
            }
            $printer->text(" \n");
            $printer->cut();
            $printer->close();


            }catch(Exception $e){}
        } finally {
            return true;
        }
            return false;
    }

    public function requisitionTicket($ip, $requisition){
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        // $printer = $ip;
        if(!$printer){ return false; }

        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->stock>0){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                // $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
                // if($volumen<=0){
                //     $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
                // }
                // $summary['volumen'] = $summary['volumen'] + $volumen;
            }else{
                $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + $product->pivot->units;
            }
            return $summary;
        }, ["models"=>0, "articles"=>0, "volumen"=>0, "sinVolumen"=>0, "modelsSouldOut"=>0, "articlesSouldOut"=>0]);

        $finished_at = $requisition->log->filter(fn($log) => $log->pivot->_status=1);
        $finished_at = $finished_at[sizeof($finished_at) - 1];

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setReverseColors(true);
        $printer->setEmphasis(true);

        if($requisition->printed>0){
            $printer->setTextSize(1,1);
            $printer->text(" *** REIMPRESION *** \n");
        }else{
            $printer->setTextSize(2,2);
            $printer->text(" *** Nuevo Pedido *** \n");
        }

        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("------------------------------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(" ".$requisition->from->alias." - ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        // $printer->text("\n AGENTE:    ".$requisition->created_by->names."\n");
        $printer->text(" SOLICITUD: ".$finished_at->pivot->created_at."\n");

        if($requisition->notes){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("\n ¡¡ NOTAS !! \n");
            $printer->setReverseColors(false);
            $printer->text(" $requisition->notes \n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
        }

        $printer->text("------------------------------------------------\n\n");
        $printer->setTextSize(1,2);
        $y = 1;
        $product = collect($requisition->products);
        $product2 = collect($requisition->products);
        $groupBy = $product->filter(function($product){
            return $product->pivot->stock>0;
        })->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{ return ''; }
        })->sortKeys();
        $piso_num = 1;
        foreach($groupBy as $piso){
            $products = $piso->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            });
            if($piso_num>1){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(1,1);
                $printer->text("----------------------------------------------\n");
                $printer->text("----------------------------------------------\n");
                $printer->setTextSize(2,1);
                $printer->text("█ ".$requisition->id." ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            // foreach($products as $product){
            //     if(intval($product->pivot->stock)>0){
            //         $locations = $product->locations->reduce(function($res, $location){
            //             return $res.$location->path.",";
            //         }, '');
            //         $printer->setJustification(Printer::JUSTIFY_LEFT);
            //         $printer->setTextSize(2,1);
            //         $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
            //         $printer->setTextSize(1,1);
            //         $printer->text($product->description." \n");
            //         $amount = '';
            //         $multiple = "";
            //         switch($product->pivot->_supply_by){
            //             case 1:
            //                 $printer->text("UNIDADES SOLICITADAS: ");
            //                 break;
            //             case 2:
            //                 $printer->text("DOCENAS SOLICITADAS: ");
            //                 $multiple = 'x12';
            //                 break;
            //             case 3:
            //                 $printer->text("CAJAS SOLICITADAS: ");
            //                 $multiple = 'x'.$product->pieces;
            //                 break;
            //             case 4:
            //                 $printer->text("MEDIAS CAJAS SOLICITADAS: ");
            //                 $multiple = "x".($product->pieces/2)."";
            //                 break;
            //         }
            //         $printer->setTextSize(2,1);
            //         $printer->text($product->pivot->amount."".$multiple);
            //         $printer->setTextSize(2,2);
            //         $printer->text("[  ]");
            //         $printer->setJustification(Printer::JUSTIFY_RIGHT);
            //         $printer->setTextSize(2,2);
            //         $printer->text("{  }\n");
            //         $printer->setTextSize(1,1);
            //         $printer->text("UF: ");
            //         $printer->setTextSize(2,1);
            //         $printer->text($product->pivot->units);
            //         $printer->setTextSize(1,1);
            //         $printer->text(" - UD: ");
            //         $printer->setTextSize(2,1);
            //         $printer->text($product->pivot->stock."\n");
            //         if($product->pivot->comments){
            //             $printer->setTextSize(1,1);
            //             $printer->text("Notas: ".$product->pivot->comments."\n");
            //         }
            //         $printer->feed(1);
            //         $y++;
            //     }
            // }
            // $piso_num++;
        // }
        // if($requisition->_type==3 || $requisition->_type==4 || $requisition->_type==1){
            // $printer->setTextSize(1,1);
            $agotados = $product2->filter(function($product){
                return $product->pivot->stock<=0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            if(count($agotados)>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("AGOTADOS \n");
                $printer->setReverseColors(false);
                $y = 1;
                $piso_num = 1;
                foreach($agotados as $piso){
                    $products = $piso->sortByDesc(function($product){
                        if(count($product->locations)>0){
                            return $product->locations[0]->path;
                        }
                        return '';
                    });
                    if($piso_num>1){
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(1,1);
                        $printer->text("----------------------------------------------\n");
                        $printer->text("----------------------------------------------\n");
                        $printer->setTextSize(2,1);
                        $printer->text("█ ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                        $printer->setTextSize(1,1);
                        $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                        $printer->feed(1);
                    }
                    foreach($products as $product){
                        if(intval($product->pivot->stock)<=0){
                            $locations = $product->locations->reduce(function($res, $location){
                                return $res.$location->path.",";
                            }, '');
                            $printer->setJustification(Printer::JUSTIFY_LEFT);
                            $printer->setTextSize(2,1);
                            $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                            $printer->setTextSize(1,1);
                            $printer->text($product->description." \n");
                            $amount = '';
                            $multiple = "";
                            switch($product->pivot->_supply_by){
                                case 1:
                                    $printer->text("UNIDADES SOLICITADAS: ");
                                    break;
                                case 2:
                                    $printer->text("DOCENAS SOLICITADAS: ");
                                    $multiple = 'x12';
                                    break;
                                case 3:
                                    $printer->text("CAJAS SOLICITADAS: ");
                                    $multiple = 'x'.$product->pieces;
                                    break;
                                case 4:
                                    $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                    $multiple = "x".($product->pieces/2)."";
                                    break;
                            }
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->amount."".$multiple);
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $printer->setTextSize(2,2);
                            $printer->text("{  }\n");
                            $printer->setTextSize(1,1);
                            $printer->text("UF: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" - UD: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->stock."\n");
                            if($product->pivot->comments){
                                $printer->setTextSize(1,1);
                                $printer->text("Notas: ".$product->pivot->comments."\n");
                            }
                            $printer->feed(1);
                            $y++;
                        }
                    }
                    $piso_num++;
                }
            }
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->text("Volumen ".$summary['volumen']." m^3\n");
        $printer->text($summary['sinVolumen']." cajas sin contabilizar\n");
        if($summary['articlesSouldOut']>0){
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Modelos agotados: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['modelsSouldOut']."\n");
            $printer->setTextSize(1,1);
            $printer->text("Piezas agotadas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articlesSouldOut'])."\n");
        }
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(50);
        $printer->setBarcodeWidth(2);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function PartitionTicket($ip,$requisition){
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        if(!$printer){ return false; }
        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->stock>0){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                // $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
                // if($volumen<=0){
                //     $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
                // }
                // $summary['volumen'] = $summary['volumen'] + $volumen;
            }else{
                $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + $product->pivot->units;
            }
            return $summary;
        }, ["models"=>0, "articles"=>0, "volumen"=>0, "sinVolumen"=>0, "modelsSouldOut"=>0, "articlesSouldOut"=>0]);

        // $finished_at = $requisition->log->filter(fn($log) => $log->pivot->_status=1);
        // $finished_at = $finished_at[sizeof($finished_at) - 1];

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setReverseColors(true);
        $printer->setEmphasis(true);

        if($requisition->printed>0){
            $printer->setTextSize(1,1);
            $printer->text(" *** REIMPRESION PARTICION *** \n");
        }else{
            $printer->setTextSize(2,2);
            $printer->text(" *** Particion  *** \n");
        }

        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("------------------------------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(" ".$requisition->requisition->from->alias." - ".$requisition->requisition->id." \n");
        // $printer->text("Particion ". " - ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->text("\n PARTICION ". " - ".$requisition->id." \n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->setTextSize(1,1);
        $printer->setReverseColors(true);
        $printer->text("\n NOTAS:    ".$requisition->requisition->notes."\n");
        $printer->setReverseColors(false);
        // $printer->text("\n PARTICION ". " - ".$requisition->id." \n");
        $printer->text("\n AGENTE:    ".$requisition->requisition->created_by->names."\n");
        // $printer->text("\n SURTIDOR:    ".$requisition->_suplier."\n");
        // $printer->text(" SOLICITUD: ".$finished_at->pivot->created_at."\n");

        if($requisition->notes){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("\n ¡¡ NOTAS !! \n");
            $printer->setReverseColors(false);
            $printer->text("$requisition->requisition->notes"." \n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
        }
        $printer->text("------------------------------------------------\n\n");
        $printer->setTextSize(1,2);
        $y = 1;
        $product = collect($requisition->products);
        $product2 = collect($requisition->products);
        $groupBy = $product->filter(function($product){
            return $product->pivot->stock>0;
        })->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{ return ''; }
        })->sortKeys();
        $piso_num = 1;
        foreach($groupBy as $piso){
            $products = $piso->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            });
            if($piso_num>1){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(1,1);
                $printer->text("----------------------------------------------\n");
                $printer->text("----------------------------------------------\n");
                $printer->setTextSize(2,1);
                $printer->text("█ ".$requisition->requisition->id." ".$requisition->requisition->to->alias." >>> ".$requisition->requisition->from->alias." █\n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            foreach($products as $product){
                if(intval($product->pivot->stock)>0){
                    $locations = $product->locations->reduce(function($res, $location){
                        return $res.$location->path.",";
                    }, '');
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(2,1);
                    $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text($product->description." \n");
                    $amount = '';
                    $multiple = "";
                    switch($product->pivot->_supply_by){
                        case 1:
                            $printer->text("UNIDADES SOLICITADAS: ");
                            break;
                        case 2:
                            $printer->text("DOCENAS SOLICITADAS: ");
                            $multiple = 'x12';
                            break;
                        case 3:
                            $printer->text("CAJAS SOLICITADAS: ");
                            $multiple = 'x'.$product->pieces;
                            break;
                        case 4:
                            $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                            $multiple = "x".($product->pieces/2)."";
                            break;
                    }
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->amount."".$multiple);
                    $printer->setTextSize(2,2);
                    $printer->text("[  ]");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setTextSize(2,2);
                    $printer->text("{  }\n");
                    $printer->setTextSize(1,1);
                    $printer->text("UF: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->units);
                    $printer->setTextSize(1,1);
                    $printer->text(" - UD: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->stock."\n");
                    if($product->pivot->comments){
                        $printer->setTextSize(1,1);
                        $printer->text("Notas: ".$product->pivot->comments."\n");
                    }
                    $printer->feed(1);
                    $y++;
                }
            }
            $piso_num++;
        }
        if($requisition->_type==3 || $requisition->_type==4 || $requisition->_type==1){
            $printer->setTextSize(1,1);
            $agotados = $product2->filter(function($product){
                return $product->pivot->stock<=0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            if(count($agotados)>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("AGOTADOS \n");
                $printer->setReverseColors(false);
                $y = 1;
                $piso_num = 1;
                foreach($agotados as $piso){
                    $products = $piso->sortByDesc(function($product){
                        if(count($product->locations)>0){
                            return $product->locations[0]->path;
                        }
                        return '';
                    });
                    if($piso_num>1){
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(1,1);
                        $printer->text("----------------------------------------------\n");
                        $printer->text("----------------------------------------------\n");
                        $printer->setTextSize(2,1);
                        $printer->text("█ ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                        $printer->setTextSize(1,1);
                        $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                        $printer->feed(1);
                    }
                    foreach($products as $product){
                        if(intval($product->pivot->stock)<=0){
                            $locations = $product->locations->reduce(function($res, $location){
                                return $res.$location->path.",";
                            }, '');
                            $printer->setJustification(Printer::JUSTIFY_LEFT);
                            $printer->setTextSize(2,1);
                            $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                            $printer->setTextSize(1,1);
                            $printer->text($product->description." \n");
                            $amount = '';
                            $multiple = "";
                            switch($product->pivot->_supply_by){
                                case 1:
                                    $printer->text("UNIDADES SOLICITADAS: ");
                                    break;
                                case 2:
                                    $printer->text("DOCENAS SOLICITADAS: ");
                                    $multiple = 'x12';
                                    break;
                                case 3:
                                    $printer->text("CAJAS SOLICITADAS: ");
                                    $multiple = 'x'.$product->pieces;
                                    break;
                                case 4:
                                    $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                    $multiple = "x".($product->pieces/2)."";
                                    break;
                            }
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->amount."".$multiple);
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $printer->setTextSize(2,2);
                            $printer->text("{  }\n");
                            $printer->setTextSize(1,1);
                            $printer->text("UF: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" - UD: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->stock."\n");
                            if($product->pivot->comments){
                                $printer->setTextSize(1,1);
                                $printer->text("Notas: ".$product->pivot->comments."\n");
                            }
                            $printer->feed(1);
                            $y++;
                        }
                    }
                    $piso_num++;
                }
            }
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->text("Volumen ".$summary['volumen']." m^3\n");
        $printer->text($summary['sinVolumen']." cajas sin contabilizar\n");
        if($summary['articlesSouldOut']>0){
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Modelos agotados: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['modelsSouldOut']."\n");
            $printer->setTextSize(1,1);
            $printer->text("Piezas agotadas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articlesSouldOut'])."\n");
        }
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(50);
        $printer->setBarcodeWidth(2);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function PrintAttention(Request $request){
        // return 'holi';
        $amount = $request->amount;
        $ip = $request->print;
        $staff = $request->staff;
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        if(!$printer){ return false; }

        for ($i = 0; $i < $amount; $i++) {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setEmphasis(false);
            $printer->setTextSize(1,1);
            $printer->text("------------------------------------------------\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(1,2);
            $printer->text("\n AGENTE:".$staff['complete_name']."\n");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(3,2);
            $printer->text("\n ID:".$staff['id_tpv']."\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(1,1);
            $printer->text("------------------------------------------------\n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setBarcodeHeight(155);
            $printer->setBarcodeWidth(255);
            $printer->barcode($staff['id_tpv']);
            $printer->feed(1);
            $printer->text("GRUPO VIZCARRA\n");
            $printer->feed(1);
            $printer->cut();
        }
        $printer->close();
        return true;
    }

    public function previewRequisition($ip,$requisition){
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);

        $summary = collect($requisition['products'])->reduce(function($summary, $product){
            if($product['sucursal'] > 0){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + 1;
            }else{
                $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + 1;
            }
            return $summary;
        }, ["models"=>0, "articles"=>0,"modelsSouldOut"=>0, "articlesSouldOut"=>0]);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setReverseColors(true);
        $printer->setEmphasis(true);


            $printer->setTextSize(2,2);
            $printer->text(" *** Previsualizacion *** \n");

        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("------------------------------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(" ".$requisition['from']['alias']." \n");
        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        // $printer->text("\n AGENTE:    ".$requisition->created_by->names."\n");
        // $printer->text(" SOLICITUD: ". new \DateTime()."\n");

        // if($requisition->notes){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            // $printer->text("\n ¡¡ NOTAS !! \n");
            $printer->setReverseColors(false);
            // $printer->text(" $requisition->notes \n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
        // }

        $printer->text("------------------------------------------------\n\n");
        $printer->setTextSize(1,2);
        $y = 1;
        $products = $requisition['products'];
            foreach($products as $product){
                    $locations = isset($product['locations']) ? collect($product['locations'])->reduce(function($res, $location){
                        return $res.$location['path'].",";
                    }, '') : '';
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(2,1);
                    // $printer->text($y."█ "."\n█ ".$product['code']." █\n");
                    $printer->text($y."█ ".trim($locations)."\n█ ".$product['code']." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text($product['description']." \n");
                    // $amount = '';
                    // $multiple = "";
                    // switch($product->pivot->_supply_by){
                    //     case 1:
                    //         $printer->text("UNIDADES SOLICITADAS: ");
                    //         break;
                    //     case 2:
                    //         $printer->text("DOCENAS SOLICITADAS: ");
                    //         $multiple = 'x12';
                    //         break;
                    //     case 3:
                            $printer->text("CAJAS SOLICITADAS: ");
                            $multiple = 'x'.$product['pieces'];
                            // break;
                    //     case 4:
                    //         $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                    //         $multiple = "x".($product->pieces/2)."";
                    //         break;
                    // }
                    $printer->setTextSize(2,1);
                    $printer->text($product['required']."".$multiple);
                    $printer->setTextSize(2,2);
                    $printer->text("[  ]");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setTextSize(2,2);
                    $printer->text("{  }\n");
                    $printer->setTextSize(1,1);
                    $printer->text("PXC: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product['pieces']."\n");
                    $printer->setTextSize(1,1);
                    $printer->text("Min : ");
                    $printer->text($product['min']." ");
                    $printer->text("Max : ");
                    $printer->text($product['max']."\n");
                    $printer->setTextSize(1,1);
                    $printer->text("S-> Sucursal: ");
                    $printer->setTextSize(1,1);
                    $printer->text($product['sucursal']."\n");
                    $printer->text("S-> Texcoco CJ: ");
                    $printer->setTextSize(1,1);
                    $printer->text($product['texcoco']."\n");
                    $printer->text("S-> Cedis CJ: ");
                    $printer->setTextSize(1,1);
                    $printer->text($product['cedis']."\n");
                    $printer->text("S-> Brasil CJ: ");
                    $printer->setTextSize(1,1);
                    $printer->text(isset($product['brasil']) ? $product['brasil'] : 0 ."\n");
                    // if($product->pivot->comments){
                    //     $printer->setTextSize(1,1);
                    //     $printer->text("Notas: ".$product->pivot->comments."\n");
                    // }
                    $printer->feed(1);
                    $y++;

            }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        // $printer->text("Volumen ".$summary['volumen']." m^3\n");
        // $printer->text($summary['sinVolumen']." cajas sin contabilizar\n");
        if($summary['articlesSouldOut']>0){
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Modelos agotados: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['modelsSouldOut']."\n");
            $printer->setTextSize(1,1);
            $printer->text("Piezas agotadas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articlesSouldOut'])."\n");
        }
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        // $printer->setBarcodeHeight($this->barcode_height);
        // $printer->setBarcodeWidth($this->barcode_width);
        // $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function orderReceipt($ip, $order, $cash){
        $connector = new NetworkPrintConnector($ip, 9100, 3);
        $printer = new Printer($connector);
        $summary = $order->products->reduce(function($summary, $product){
            $summary['models'] = $summary['models'] + 1;
            $summary['units'] = $summary['units'] + $product->pivot->units;
            return $summary;
        }, ["models" => 0, 'units' => 0]);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if($order->_order){
            $printer->setTextSize(2,2);
            $printer->setEmphasis(true);
            $printer->setReverseColors(true);
            $printer->setTextSize(2,2);
            $printer->text("ANEXO ".$order->_order." \n");
            $printer->setEmphasis(false);
            $printer->setReverseColors(false);
        }
        $printer->setTextSize(1,2);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Gracias por su pedido ".$order->name.", te esperamos en\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("---------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setEmphasis(true);
        $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("---------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        $printer->text(" Lo atendio: ".$order->created_by->names. " ".$order->created_by->surname_pat." \n");
        $printer->text(" Fecha/Hora: ".$cash->pivot->created_at." \n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(1,1);
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['units']."\n");
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("---------------------------\n");
        $printer->setBarcodeHeight(50);
        $printer->setBarcodeWidth(2);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->barcode($order->id);
        $printer->feed(1);
        $printer->text($order->id."\n");
        $printer->text($order->from->name.", GRUPO VIZCARRA");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
        try{
        } catch(\Exception $e){
            return false;
        }
    }

    public function orderTicket2($ip,OrderVA $order, $cash, $in_coming = null){
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
            $summary = $order->products->reduce(function($summary, $product){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                return $summary;
            }, ["models" => 0, "articles" => 0]);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            if($order->printed>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("REIMPRESION \n");
                $printer->setReverseColors(false);
            }

            if($order->_order){

                $printer->text("ANEXO ");
                $printer->setReverseColors(true);
                $printer->setTextSize(2,2);
                $printer->text($order->_order." \n");
                $printer->setEmphasis(false);
                $printer->setReverseColors(false);
            }

            $printer->setTextSize(1,2);
            $printer->text("Pedido para : \n");
            $printer->setTextSize(2,2);
            $printer->text($order->name." \n");
            // $printer->text("Pedido para:".$order->name." \n");
            $printer->setTextSize(1,1);
            $printer->text(" Vendedor: ".$order->created_by->names. " ".$order->created_by->surname_pat." \n");
            // $printer->setTextSize(1,1);
            if($order->created_by->id_tpv){
                $printer->setBarcodeHeight(45);
                $printer->setBarcodeWidth(2);
                $printer->barcode($order->created_by->id_tpv);
                $printer->text("(".$order->created_by->id_tpv.")"." \n");
            }
            $printer->setTextSize(2,2);
            $printer->text("--  ".$cash->pivot->responsable->name."  --\n");
            $printer->setTextSize(1,1);
            $printer->text("----------------------------------------\n");
            $created_at = is_null($in_coming) ? date('d/m/Y H:i', time()) : $cash->pivot->created_at;
            $printer->text(" Fecha/Hora: ".$created_at." \n");
            $printer->text("Modelos: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['models']);
            $printer->setTextSize(1,1);
            $printer->text(" Piezas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articles'])."\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("----------------------------------------\n");
            $y = 1;
            $products = $order->products->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                return $product->pivot->_supply_by;
            })->sortKeysDesc();
            $x = 1;
            foreach($products as $key => $el){
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->setReverseColors(true);
                $printer->setTextSize(2,1);
                switch($key){
                    case 1:
                        $printer->text(" Piezas - ".$x."/".count($products));
                        break;
                    case 2:
                        $printer->text(" Docenas - ".$x."/".count($products));
                        break;
                    case 3:
                        $printer->text(" Cajas - ".$x."/".count($products));
                        break;
                    case 4:
                        $printer->text(" Medias cajas - ".$x."/".count($products));
                        break;
                }
                $printer->setReverseColors(false);
                $printer->text(" ".$order->id."\n");
                foreach($el as $key => $product){
                    $this->printBodyTicket($printer, $product, $key+1);
                }
                $printer->setTextSize(1,1);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("----------------------------------------\n");
                $printer->text("----------------------------------------\n");
                $printer->feed(1);
                $x++;
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setBarcodeHeight(50);
            $printer->setBarcodeWidth(2);
            $printer->barcode($order->id);
            $printer->feed(1);
            $printer->setTextSize(1,1);
            $printer->text($order->id."\n");
            $printer->text("GRUPO VIZCARRA\n");
            $printer->feed(1);
            $printer->cut();
            $printer->close();
            return true;
        } catch(\Exception $e){
            return false;
        }
    }

    public function printBodyTicket($printer, $product, $y){
        $locations = $product->locations->reduce(function($res, $location){
            return $res.$location->path.",";
        }, '');
        $stock =$product->stocks;
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setFont(Printer::FONT_B);
        $printer->setTextSize(3,1);
        $printer->text($y."█ ".trim($locations)."\n█ ");
        $printer->text($product->code." █ \n");
        $printer->setEmphasis(true);
        $printer->setTextSize(1,1);
        $printer->text($product->description."\n");
        $printer->setFont(Printer::FONT_A);
        switch($product->pivot->_supply_by){
            case 1:
                $printer->text("UNIDADES SOLICITADAS: ");
                $printer->setTextSize(2,1);
                break;
            case 2:
                $printer->text("DOCENAS SOLICITADAS: ");
                $printer->setTextSize(2,1);
                $printer->text($product->pivot->amount.'x12= ');
                break;
            case 3:
                $printer->text("CAJAS SOLICITADAS: ");
                $units = $product->pivot->units / $product->pivot->amount;
                $printer->setTextSize(2,1);
                $printer->text($product->pivot->amount."x".$units."= ");
                break;
            case 4:
                $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                $units = ($product->pivot->units / $product->pivot->amount)/2;
                $printer->setTextSize(2,1);
                $printer->text($product->pivot->amount."x".$units."= ");
                break;
        }
        $printer->setReverseColors(true);
        $printer->text(" ".$product->pivot->units."pz");
        $printer->setReverseColors(false);
        $printer->text(" D->".$stock[0]->pivot->gen." \n");
        if($product->pivot->comments){
            $printer->setTextSize(1,1);
            $printer->setReverseColors(true);
            $printer->text("Notas: ".$product->pivot->comments."\n");
            $printer->setReverseColors(false);
        }
        $printer->setEmphasis(false);
        $printer->feed(1);
    }

    public function requisitionTicketCatalog($ip, $requisition){
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        // $printer = $ip;
        if(!$printer){ return false; }

        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->stock>0){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                // $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
                // if($volumen<=0){
                //     $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
                // }
                // $summary['volumen'] = $summary['volumen'] + $volumen;
            }else{
                $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + $product->pivot->units;
            }
            return $summary;
        }, ["models"=>0, "articles"=>0, "volumen"=>0, "sinVolumen"=>0, "modelsSouldOut"=>0, "articlesSouldOut"=>0]);

        $finished_at = $requisition->log->filter(fn($log) => $log->pivot->_status=1);
        $finished_at = $finished_at[sizeof($finished_at) - 1];

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setReverseColors(true);
        $printer->setEmphasis(true);

        if($requisition->printed>0){
            $printer->setTextSize(1,1);
            $printer->text(" *** REIMPRESION *** \n");
        }else{
            $printer->setTextSize(2,2);
            $printer->text(" *** Nuevo Pedido *** \n");
        }

        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("------------------------------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(" ".$requisition->from->alias." - ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setTextSize(1,1);
        // $printer->text("\n AGENTE:    ".$requisition->created_by->names."\n");
        $printer->text(" SOLICITUD: ".$finished_at->pivot->created_at."\n");

        if($requisition->notes){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("\n ¡¡ NOTAS !! \n");
            $printer->setReverseColors(false);
            $printer->text(" $requisition->notes \n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
        }

        $printer->text("------------------------------------------------\n\n");
        $printer->setTextSize(1,2);
        $y = 1;
        $product = collect($requisition->products);
        $product2 = collect($requisition->products);
        $groupBy = $product->filter(function($product){
            return $product->pivot->stock>0;
        })->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{ return ''; }
        })->sortKeys();
        $piso_num = 1;
        foreach($groupBy as $piso){
            $products = $piso->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            });
            if($piso_num>1){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(1,1);
                $printer->text("----------------------------------------------\n");
                $printer->text("----------------------------------------------\n");
                $printer->setTextSize(2,1);
                $printer->text("█ ".$requisition->id." ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            foreach($products as $product){
                if(intval($product->pivot->stock)>0){
                    $locations = $product->locations->reduce(function($res, $location){
                        return $res.$location->path.",";
                    }, '');
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(2,1);
                    $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text($product->description." \n");
                    $amount = '';
                    $multiple = "";
                    switch($product->pivot->_supply_by){
                        case 1:
                            $printer->text("UNIDADES SOLICITADAS: ");
                            break;
                        case 2:
                            $printer->text("DOCENAS SOLICITADAS: ");
                            $multiple = 'x12';
                            break;
                        case 3:
                            $printer->text("CAJAS SOLICITADAS: ");
                            $multiple = 'x'.$product->pieces;
                            break;
                        case 4:
                            $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                            $multiple = "x".($product->pieces/2)."";
                            break;
                    }
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->amount."".$multiple);
                    $printer->setTextSize(2,2);
                    $printer->text("[  ]");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setTextSize(2,2);
                    $printer->text("{  }\n");
                    $printer->setTextSize(1,1);
                    $printer->text("UF: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->units);
                    $printer->setTextSize(1,1);
                    $printer->text(" - UD: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->stock."\n");
                    if($product->pivot->comments){
                        $printer->setTextSize(1,1);
                        $printer->text("Notas: ".$product->pivot->comments."\n");
                    }
                    $printer->feed(1);
                    $y++;
                }
            }
            $piso_num++;
        }
        if($requisition->_type==3 || $requisition->_type==4 || $requisition->_type==1){
            $printer->setTextSize(1,1);
            $agotados = $product2->filter(function($product){
                return $product->pivot->stock<=0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            if(count($agotados)>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("AGOTADOS \n");
                $printer->setReverseColors(false);
                $y = 1;
                $piso_num = 1;
                foreach($agotados as $piso){
                    $products = $piso->sortByDesc(function($product){
                        if(count($product->locations)>0){
                            return $product->locations[0]->path;
                        }
                        return '';
                    });
                    if($piso_num>1){
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(1,1);
                        $printer->text("----------------------------------------------\n");
                        $printer->text("----------------------------------------------\n");
                        $printer->setTextSize(2,1);
                        $printer->text("█ ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                        $printer->setTextSize(1,1);
                        $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                        $printer->feed(1);
                    }
                    foreach($products as $product){
                        if(intval($product->pivot->stock)<=0){
                            $locations = $product->locations->reduce(function($res, $location){
                                return $res.$location->path.",";
                            }, '');
                            $printer->setJustification(Printer::JUSTIFY_LEFT);
                            $printer->setTextSize(2,1);
                            $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                            $printer->setTextSize(1,1);
                            $printer->text($product->description." \n");
                            $amount = '';
                            $multiple = "";
                            switch($product->pivot->_supply_by){
                                case 1:
                                    $printer->text("UNIDADES SOLICITADAS: ");
                                    break;
                                case 2:
                                    $printer->text("DOCENAS SOLICITADAS: ");
                                    $multiple = 'x12';
                                    break;
                                case 3:
                                    $printer->text("CAJAS SOLICITADAS: ");
                                    $multiple = 'x'.$product->pieces;
                                    break;
                                case 4:
                                    $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                    $multiple = "x".($product->pieces/2)."";
                                    break;
                            }
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->amount."".$multiple);
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $printer->setTextSize(2,2);
                            $printer->text("{  }\n");
                            $printer->setTextSize(1,1);
                            $printer->text("UF: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" - UD: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->stock."\n");
                            if($product->pivot->comments){
                                $printer->setTextSize(1,1);
                                $printer->text("Notas: ".$product->pivot->comments."\n");
                            }
                            $printer->feed(1);
                            $y++;
                        }
                    }
                    $piso_num++;
                }
            }
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->text("Volumen ".$summary['volumen']." m^3\n");
        $printer->text($summary['sinVolumen']." cajas sin contabilizar\n");
        if($summary['articlesSouldOut']>0){
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Modelos agotados: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['modelsSouldOut']."\n");
            $printer->setTextSize(1,1);
            $printer->text("Piezas agotadas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articlesSouldOut'])."\n");
        }
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(50);
        $printer->setBarcodeWidth(2);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function PartitionTicketCatalog($ip,$requisition){
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        if(!$printer){ return false; }
        $summary = $requisition->products->reduce(function($summary, $product){
            if($product->pivot->stock>0){
                $summary['models'] = $summary['models'] + 1;
                $summary['articles'] = $summary['articles'] + $product->pivot->units;
                // $volumen = ($product->dimensions->length * $product->dimensions->height * $product->dimensions->width) / 1000000;
                // if($volumen<=0){
                //     $summary['sinVolumen'] = $summary['sinVolumen'] + $product->pivot->units;
                // }
                // $summary['volumen'] = $summary['volumen'] + $volumen;
            }else{
                $summary['modelsSouldOut'] = $summary['modelsSouldOut'] + 1;
                $summary['articlesSouldOut'] = $summary['articlesSouldOut'] + $product->pivot->units;
            }
            return $summary;
        }, ["models"=>0, "articles"=>0, "volumen"=>0, "sinVolumen"=>0, "modelsSouldOut"=>0, "articlesSouldOut"=>0]);

        // $finished_at = $requisition->log->filter(fn($log) => $log->pivot->_status=1);
        // $finished_at = $finished_at[sizeof($finished_at) - 1];

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setReverseColors(true);
        $printer->setEmphasis(true);

        if($requisition->printed>0){
            $printer->setTextSize(1,1);
            $printer->text(" *** REIMPRESION PARTICION *** \n");
        }else{
            $printer->setTextSize(2,2);
            $printer->text(" *** Particion  *** \n");
        }

        $printer->setReverseColors(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(false);
        $printer->setTextSize(1,1);
        $printer->text("------------------------------------------------\n");
        $printer->setTextSize(2,2);
        $printer->setReverseColors(true);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text(" ".$requisition->requisition->from->alias." - ".$requisition->requisition->id." \n");
        // $printer->text("Particion ". " - ".$requisition->id." \n");
        $printer->setReverseColors(false);
        $printer->text("\n PARTICION ". " - ".$requisition->id." \n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);


        $printer->setReverseColors(true);
        $printer->text("\n NOTAS:    ".$requisition->requisition->notes."\n");
        $printer->setReverseColors(false);
        $printer->setTextSize(1,1);
        // $printer->text("\n PARTICION ". " - ".$requisition->id." \n");
        $printer->text("\n AGENTE:    ".$requisition->requisition->created_by->names."\n");
        // $printer->text("\n SURTIDOR:    ".$requisition->_suplier."\n");
        // $printer->text(" SOLICITUD: ".$finished_at->pivot->created_at."\n");

        if($requisition->notes){
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->setTextSize(2,1);
            $printer->setReverseColors(true);
            $printer->text("\n ¡¡ NOTAS !! \n");
            $printer->setReverseColors(false);
            $printer->text("$requisition->requisition->notes"." \n\n");
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
        }
        $printer->text("------------------------------------------------\n\n");
        $printer->setTextSize(1,2);
        $y = 1;
        $product = collect($requisition->products);
        $product2 = collect($requisition->products);
        $groupBy = $product->filter(function($product){
            return $product->pivot->stock>0;
        })->map(function($product){
            $product->locations->sortBy('path');
            return $product;
        })->groupBy(function($product){
            if(count($product->locations)>0){
                return explode('-',$product->locations[0]->path)[0];
            }else{ return ''; }
        })->sortKeys();
        $piso_num = 1;
        foreach($groupBy as $piso){
            $products = $piso->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            });
            if($piso_num>1){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->setTextSize(1,1);
                $printer->text("----------------------------------------------\n");
                $printer->text("----------------------------------------------\n");
                $printer->setTextSize(2,1);
                $printer->text("█ ".$requisition->requisition->id." ".$requisition->requisition->to->alias." >>> ".$requisition->requisition->from->alias." █\n");
                $printer->setTextSize(1,1);
                $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                $printer->feed(1);
            }
            foreach($products as $product){
                if(intval($product->pivot->stock)>0){
                    $locations = $product->locations->reduce(function($res, $location){
                        return $res.$location->path.",";
                    }, '');
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(2,1);
                    $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text($product->description." \n");
                    $amount = '';
                    $multiple = "";
                    switch($product->pivot->_supply_by){
                        case 1:
                            $printer->text("UNIDADES SOLICITADAS: ");
                            break;
                        case 2:
                            $printer->text("DOCENAS SOLICITADAS: ");
                            $multiple = 'x12';
                            break;
                        case 3:
                            $printer->text("CAJAS SOLICITADAS: ");
                            $multiple = 'x'.$product->pieces;
                            break;
                        case 4:
                            $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                            $multiple = "x".($product->pieces/2)."";
                            break;
                    }
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->amount."".$multiple);
                    $printer->setTextSize(2,2);
                    $printer->text("[  ]");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $printer->setTextSize(2,2);
                    $printer->text("{  }\n");
                    $printer->setTextSize(1,1);
                    $printer->text("UF: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->units);
                    $printer->setTextSize(1,1);
                    $printer->text(" - UD: ");
                    $printer->setTextSize(2,1);
                    $printer->text($product->pivot->stock."\n");
                    if($product->pivot->comments){
                        $printer->setTextSize(1,1);
                        $printer->text("Notas: ".$product->pivot->comments."\n");
                    }
                    $printer->feed(1);
                    $y++;
                }
            }
            $piso_num++;
        }
        if($requisition->_type==3 || $requisition->_type==4 || $requisition->_type==1){
            $printer->setTextSize(1,1);
            $agotados = $product2->filter(function($product){
                return $product->pivot->stock<=0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            if(count($agotados)>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("AGOTADOS \n");
                $printer->setReverseColors(false);
                $y = 1;
                $piso_num = 1;
                foreach($agotados as $piso){
                    $products = $piso->sortByDesc(function($product){
                        if(count($product->locations)>0){
                            return $product->locations[0]->path;
                        }
                        return '';
                    });
                    if($piso_num>1){
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(1,1);
                        $printer->text("----------------------------------------------\n");
                        $printer->text("----------------------------------------------\n");
                        $printer->setTextSize(2,1);
                        $printer->text("█ ".$requisition->to->alias." >>> ".$requisition->from->alias." █\n");
                        $printer->setTextSize(1,1);
                        $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                        $printer->feed(1);
                    }
                    foreach($products as $product){
                        if(intval($product->pivot->stock)<=0){
                            $locations = $product->locations->reduce(function($res, $location){
                                return $res.$location->path.",";
                            }, '');
                            $printer->setJustification(Printer::JUSTIFY_LEFT);
                            $printer->setTextSize(2,1);
                            $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                            $printer->setTextSize(1,1);
                            $printer->text($product->description." \n");
                            $amount = '';
                            $multiple = "";
                            switch($product->pivot->_supply_by){
                                case 1:
                                    $printer->text("UNIDADES SOLICITADAS: ");
                                    break;
                                case 2:
                                    $printer->text("DOCENAS SOLICITADAS: ");
                                    $multiple = 'x12';
                                    break;
                                case 3:
                                    $printer->text("CAJAS SOLICITADAS: ");
                                    $multiple = 'x'.$product->pieces;
                                    break;
                                case 4:
                                    $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                    $multiple = "x".($product->pieces/2)."";
                                    break;
                            }
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->amount."".$multiple);
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $printer->setTextSize(2,2);
                            $printer->text("{  }\n");
                            $printer->setTextSize(1,1);
                            $printer->text("UF: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" - UD: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->stock."\n");
                            if($product->pivot->comments){
                                $printer->setTextSize(1,1);
                                $printer->text("Notas: ".$product->pivot->comments."\n");
                            }
                            $printer->feed(1);
                            $y++;
                        }
                    }
                    $piso_num++;
                }
            }
        }
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(1,1);
        $printer->text("--------------------------------------------\n");
        $printer->text("Modelos: ");
        $printer->setTextSize(2,1);
        $printer->text($summary['models']);
        $printer->setTextSize(1,1);
        $printer->text(" Piezas: ");
        $printer->setTextSize(2,1);
        $printer->text(round($summary['articles'])."\n");
        $printer->setTextSize(1,1);
        $printer->text("Volumen ".$summary['volumen']." m^3\n");
        $printer->text($summary['sinVolumen']." cajas sin contabilizar\n");
        if($summary['articlesSouldOut']>0){
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Modelos agotados: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['modelsSouldOut']."\n");
            $printer->setTextSize(1,1);
            $printer->text("Piezas agotadas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articlesSouldOut'])."\n");
        }
        $printer->setTextSize(1,1);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("--------------------------------------------\n");
        $printer->setBarcodeHeight(50);
        $printer->setBarcodeWidth(2);
        $printer->barcode($requisition->id);
        $printer->feed(1);
        $printer->text("GRUPO VIZCARRA\n");
        $printer->feed(1);
        $printer->cut();
        $printer->close();
        return true;
    }

    public function PartitionDirect($ip,$requisition,OrderVA $order){// PARTICION TICKET DE SUCURSAL
        try{
            $connector = new NetworkPrintConnector($ip, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return false;}
        if(!$printer){ return false; }
        $summary = $requisition->products->reduce(function($acc, $product){
            if($product->pivot->stock>0){
                $acc['models'] = $acc['models'] + 1;
                $acc['articles'] = $acc['articles'] + $product->pivot->units;
            }else{
                $acc['modelsSouldOut'] = $acc['modelsSouldOut'] + 1;
                $acc['articlesSouldOut'] = $acc['articlesSouldOut'] + $product->pivot->units;
            }
            return $acc;
        }, ["models"=>0, "articles"=>0, "volumen"=>0, "sinVolumen"=>0, "modelsSouldOut"=>0, "articlesSouldOut"=>0]);
            // $order->load(['history']);
            // $caja = collect($order->history)->first(function ($log) {
            //     return $log->pivot->_status == 2;
            // });
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setReverseColors(true);
            $printer->setEmphasis(true);
            if($requisition->printed>0){
                $printer->setTextSize(1,1);
                $printer->text(" *** REIMPRESION PARTICION *** \n");
            }else{
                $printer->setTextSize(2,2);
                $printer->text(" *** Particion  *** \n");
            }

            $printer->setReverseColors(false);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setEmphasis(false);
            $printer->setTextSize(1,1);
            $printer->text("------------------------------------------------\n");
            $printer->setTextSize(2,2);
            $printer->setReverseColors(true);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text(" ".$requisition->requisition->from->alias." - ".$requisition->requisition->id." \n");
            $printer->setReverseColors(false);
            $printer->text("\n PARTICION ". " - ".$requisition->id." \n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(1,1);
            $printer->setReverseColors(true);
            $printer->text("\n NOTAS:    ".$requisition->requisition->notes."\n");
            $printer->setReverseColors(false);
            $printer->text("\n AGENTE:    ".$requisition->requisition->created_by->names."\n");
            // $printer->text("\n ENTREGA:   ".$caja?->responsable->name."\n");
            if($requisition->notes){
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->selectPrintMode(Printer::MODE_FONT_B);
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("\n ¡¡ NOTAS !! \n");
                $printer->setReverseColors(false);
                $printer->text("$requisition->requisition->notes"." \n\n");
                $printer->setTextSize(1,1);
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->selectPrintMode(Printer::MODE_FONT_A);
            }
            $printer->text("------------------------------------------------\n\n");
            $printer->setTextSize(1,2);
            $y = 1;
            $product = collect($requisition->products);

            $groupBy = $product->filter(function($product){
                return $product->pivot->stock>0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{ return ''; }
            })->sortKeys();
            $piso_num = 1;
            foreach($groupBy as $piso){
                $products = $piso->sortBy(function($product){
                    if(count($product->locations)>0){
                        $location = $product->locations[0]->path;
                        $res = '';
                        $parts = explode('-', $location);
                        foreach($parts as $part){
                            $numbers = preg_replace('/[^0-9]/', '', $part);
                            $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                            if(strlen($numbers)==1){
                                $numbers = '0'.$numbers;
                            }
                            $res = $res.$letters.$numbers.'-';
                        }
                        return $res;
                    }
                    return '';
                });
                if($piso_num>1){
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->setTextSize(1,1);
                    $printer->text("----------------------------------------------\n");
                    $printer->text("----------------------------------------------\n");
                    $printer->setTextSize(2,1);
                    $printer->text("█ ".$requisition->requisition->id." ".$requisition->requisition->to->alias." >>> ".$requisition->requisition->from->alias." █\n");
                    $printer->setTextSize(1,1);
                    $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                    $printer->feed(1);
                }
                foreach($products as $product){
                    if(intval($product->pivot->stock)>0){
                        $locations = $product->locations->reduce(function($res, $location){
                            return $res.$location->path.",";
                        }, '');
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(2,1);
                        $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                        $printer->setTextSize(1,1);
                        $printer->text($product->description." \n");
                        $amount = '';
                        $multiple = "";
                        switch($product->pivot->_supply_by){
                            case 1:
                                $printer->text("UNIDADES SOLICITADAS: ");
                                break;
                            case 2:
                                $printer->text("DOCENAS SOLICITADAS: ");
                                $multiple = 'x12';
                                break;
                            case 3:
                                $printer->text("CAJAS SOLICITADAS: ");
                                $multiple = 'x'.$product->pieces;
                                break;
                            case 4:
                                $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                $multiple = "x".($product->pieces/2)."";
                                break;
                        }
                        $printer->setTextSize(2,1);
                        $printer->text($product->pivot->amount."".$multiple);
                        $printer->setTextSize(2,2);
                        $printer->text("[  ]");
                        $printer->setJustification(Printer::JUSTIFY_RIGHT);
                        $printer->setTextSize(2,2);
                        $printer->text("{  }\n");
                        $printer->setTextSize(1,1);
                        $printer->text("UF: ");
                        $printer->setTextSize(2,1);
                        $printer->text($product->pivot->units);
                        $printer->setTextSize(1,1);
                        $printer->text(" - UD: ");
                        $printer->setTextSize(2,1);
                        $printer->text($product->pivot->stock."\n");
                        if($product->pivot->comments){
                            $printer->setTextSize(1,1);
                            $printer->text("Notas: ".$product->pivot->comments."\n");
                        }
                        $printer->feed(1);
                        $y++;
                    }
                }
                $piso_num++;
            }
            $printer->setTextSize(1,1);
            $product2 = collect($requisition->products);
            $agotados = $product2->filter(function($product){
                return $product->pivot->stock <= 0;
            })->map(function($product){
                $product->locations->sortBy('path');
                return $product;
            })->sortBy(function($product){
                if(count($product->locations)>0){
                    $location = $product->locations[0]->path;
                    $res = '';
                    $parts = explode('-', $location);
                    foreach($parts as $part){
                        $numbers = preg_replace('/[^0-9]/', '', $part);
                        $letters = preg_replace('/[^a-zA-Z]/', '', $part);
                        if(strlen($numbers)==1){
                            $numbers = '0'.$numbers;
                        }
                        $res = $res.$letters.$numbers.'-';
                    }
                    return $res;
                }
                return '';
            })->groupBy(function($product){
                if(count($product->locations)>0){
                    return explode('-',$product->locations[0]->path)[0];
                }else{
                    return '';
                }
            })->sortKeys();
            if(count($agotados)>0){
                $printer->setTextSize(2,1);
                $printer->setReverseColors(true);
                $printer->text("AGOTADOS \n");
                $printer->setReverseColors(false);
                $y = 1;
                $piso_num = 1;
                foreach($agotados as $piso){
                    $products = $piso->sortByDesc(function($product){
                        if(count($product->locations)>0){
                            return $product->locations[0]->path;
                        }
                        return '';
                    });
                    if($piso_num>1){
                        $printer->setJustification(Printer::JUSTIFY_LEFT);
                        $printer->setTextSize(1,1);
                        $printer->text("----------------------------------------------\n");
                        $printer->text("----------------------------------------------\n");
                        $printer->setTextSize(2,1);
                        $printer->text("█ ".$requisition->requisition->to->alias." >>> ".$requisition->requisition->from->alias." █\n");
                        $printer->setTextSize(1,1);
                        $printer->text("Complemento █ ".$piso_num." █ ".$piso_num."/".count($groupBy)."\n");
                        $printer->feed(1);
                    }
                    foreach($products as $product){
                        if(intval($product->pivot->stock)<=0){
                            $locations = $product->locations->reduce(function($res, $location){
                                return $res.$location->path.",";
                            }, '');
                            $printer->setJustification(Printer::JUSTIFY_LEFT);
                            $printer->setTextSize(2,1);
                            $printer->text($y."█ ".trim($locations)."\n█ ".$product->code." █\n");
                            $printer->setTextSize(1,1);
                            $printer->text($product->description." \n");
                            $amount = '';
                            $multiple = "";
                            switch($product->pivot->_supply_by){
                                case 1:
                                    $printer->text("UNIDADES SOLICITADAS: ");
                                    break;
                                case 2:
                                    $printer->text("DOCENAS SOLICITADAS: ");
                                    $multiple = 'x12';
                                    break;
                                case 3:
                                    $printer->text("CAJAS SOLICITADAS: ");
                                    $multiple = 'x'.$product->pieces;
                                    break;
                                case 4:
                                    $printer->text("MEDIAS CAJAS SOLICITADAS: ");
                                    $multiple = "x".($product->pieces/2)."";
                                    break;
                            }
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->amount."".$multiple);
                            $printer->setTextSize(2,2);
                            $printer->text("[  ]");
                            $printer->setJustification(Printer::JUSTIFY_RIGHT);
                            $printer->setTextSize(2,2);
                            $printer->text("{  }\n");
                            $printer->setTextSize(1,1);
                            $printer->text("UF: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->units);
                            $printer->setTextSize(1,1);
                            $printer->text(" - UD: ");
                            $printer->setTextSize(2,1);
                            $printer->text($product->pivot->stock."\n");
                            if($product->pivot->comments){
                                $printer->setTextSize(1,1);
                                $printer->text("Notas: ".$product->pivot->comments."\n");
                            }
                            $printer->feed(1);
                            $y++;
                        }
                    }
                    $piso_num++;
                }
            }
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(1,1);
            $printer->text("--------------------------------------------\n");
            $printer->text("Modelos: ");
            $printer->setTextSize(2,1);
            $printer->text($summary['models']);
            $printer->setTextSize(1,1);
            $printer->text(" Piezas: ");
            $printer->setTextSize(2,1);
            $printer->text(round($summary['articles'])."\n");
            $printer->setTextSize(1,1);
            if($summary['articlesSouldOut']>0){
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text("Modelos agotados: ");
                $printer->setTextSize(2,1);
                $printer->text($summary['modelsSouldOut']."\n");
                $printer->setTextSize(1,1);
                $printer->text("Piezas agotadas: ");
                $printer->setTextSize(2,1);
                $printer->text(round($summary['articlesSouldOut'])."\n");
            }
            $printer->setTextSize(1,1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("--------------------------------------------\n");
            $printer->setBarcodeHeight(50);
            $printer->setBarcodeWidth(2);
            $printer->barcode($requisition->id);
            $printer->feed(1);
            $printer->text("GRUPO VIZCARRA\n");
            $printer->feed(1);
            $printer->cut();
        // }
        $printer->close();
        return true;
    }

}
