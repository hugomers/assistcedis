<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductsController extends Controller
{
    public function translateWarehouses(Request $request){
        $stor =[
            'fails'=>[],
            'goals'=>[]
        ];
        $pasillo = $request->pasillo;
        $wareor = $request->origen;
        $waredes = $request->destino;
        $orders = $request->orders;
        $worids = DB::connection('vizapi')->table('orders AS O')->join('workpoints AS W','W.id','O._workpoint_from')->whereIn('O.id',$orders)->select('W.*')->first();
        $workpoint = $worids->name;
        $domain = $worids->dominio;
        $notas = "Traspaso de las comandas ".implode("-",$orders)." pasillo P-".$pasillo;
        $prord = DB::connection('vizapi')->table('product_ordered AS PO')->join('products AS P','P.id','PO._product')->whereIn('PO._order',$orders)->select('P.code AS ARTLTR','PO.amount AS CANLTR')->get();
        $import = [
            "AORTRA"=>$wareor,
            "ADETRA"=>$waredes,
            "COMTRA"=>$notas,
            "products"=>$prord
        ];

        // $url = $domain."/storetools/public/api/Products/translate";//se optiene el inicio del dominio de la sucursal
        $url = "192.168.10.61:1619"."/storetools/public/api/Products/translate";//se optiene el inicio del dominio de la sucursal
        $ch = curl_init($url);//inicio de curl
        $data = json_encode(["data" => $import]);//se codifica el arreglo de los proveedores
        //inicio de opciones de curl
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        //fin de opciones e curl
        $exec = curl_exec($ch);//se executa el curl
        $exc = json_decode($exec);//se decodifican los datos decodificados
        if(is_null($exc)){//si me regresa un null
            $stor['fails'] =["sucursal"=>$workpoint, "mssg"=>$exec];
        }else{
            // $stor['goals'][] = $store->alias." cambios hechos";//de lo contrario se almacenan en sucursales
            $stor['goals'] =["sucursal"=>$workpoint, "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas
        }
        curl_close($ch);//cirre de curl

        $res = [
            'IMPORTADO'=>$import,
            'SUCURSAL'=>$stor
        ];

        return response()->json($res);
    }
}
