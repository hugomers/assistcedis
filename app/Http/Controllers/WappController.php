<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WappController extends Controller
{
    public function restock(Request $request){
        $sucursal = $request->sucursal;

        $idsuc = DB::connection('vizapi')->table('workpoints')->where('alias',$sucursal)->value('id');
        $surtido = $this->categories($idsuc);
        $categori = json_decode($surtido);
        $faltantes = DB::connection('vizapi')->select('SELECT
        seccion,
        familia,
        COUNT(*) AS CONTEO
        FROM (
                        SELECT
                        GETSECTION(PC.id)as seccion,
                        GETFAMILY(PC.id) as familia,
                        P.id AS id,
                        P.code AS code,
                        P._unit AS unitsupply,
                        P.pieces AS ipack,
                        P.cost AS cost,
                            (SELECT stock FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product = P.id AND _status != 4 AND min > 0 AND max > 0) AS stock,
                            (SELECT min FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product = P.id) AS min,
                            (SELECT max FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product = P.id) AS max,
                            SUM(IF(PS._workpoint=1, PS.stock, 0)) AS CEDIS,
                            (SELECT SUM(stock) FROM product_stock WHERE _workpoint = 2 AND _product = P.id) AS TEXCOCO
                        FROM
                            products P
                                INNER JOIN product_categories PC ON PC.id = P._category
                                INNER JOIN product_stock PS ON PS._product = P.id
                        WHERE
                            GETSECTION(PC.id) in ('.$surtido.')
                                AND P._status != 4
                                AND (IF(PS._workpoint = 1, PS._status, 0)) = 1
                                AND ((SELECT stock FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product=P.id AND _status!=4 AND min>0 AND max>0)) IS NOT NULL
                                AND (IF((SELECT stock FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product=P.id AND _status!=4 AND min>0 AND max>0) <= (SELECT min FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product=P.id), (SELECT  max FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product = P.id) - (SELECT  stock FROM product_stock WHERE _workpoint= '.$idsuc.' AND _product = P.id AND _status != 4 AND min > 0 AND max > 0), 0)) > 0
                        GROUP BY P.code
         ) AS FAL
         GROUP BY seccion, familia');

        foreach($faltantes as $faltante){
            $seccion = $faltante->seccion;
            $familia = $faltante->familia;
            $conte = $faltante->CONTEO;

            $secc [] = $seccion." = (".$familia ." => ". $conte.")";

        }
        $res = implode(", ",$secc);

         return response()->json($res);

    }

    public function categories($id){
        switch($id){
            case 1: return '"Mochila", "Juguete"'; break;
            case 3: return '"Paraguas"'; break;
            case 4: return '"Mochila"'; break;
            case 5: return '"Mochila"'; break;
            case 6: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 7: return '"Mochila"'; break;
            case 8: return '"Calculadora", "Juguete", "Papeleria"'; break;
            case 9: return '"Mochila"'; break;
            case 10: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 11: return '"Juguete"'; break;
            case 12: return '"Calculadora", "Electronico", "Hogar"'; break;
            case 13: return '"Mochila"'; break;
            case 18: return '"Mochila", "Electronico", "Hogar"'; break;
            case 19: return '"Juguete"'; break;
            case 22: return '"Mochila"'; break;
        }
    }
}
