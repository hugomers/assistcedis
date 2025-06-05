<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function getOrder($ord){
        $order = DB::connect('vizapi')->table('orders')->where('id',$ord)->get();
        return response($order);
    }
}
