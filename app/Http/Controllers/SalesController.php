<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Stores;
use App\Models\Position;
use App\Models\Restock;
use App\Models\partitionRequisition;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function Index(){
        $stores = Stores::whereNotIn('id',[1,2,5,14,15])->get();
        return response()->json($stores);
    }
}
